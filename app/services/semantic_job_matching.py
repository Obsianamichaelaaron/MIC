import hashlib
import logging
import os
import re
import tempfile
from threading import Lock
from functools import lru_cache
from pathlib import Path

import numpy as np
import onnxruntime
import requests
from tokenizers import Tokenizer

from app.models import Applicant, JobPosting


logger = logging.getLogger(__name__)
_MODEL_LOAD_FAILURE = None
_MODEL_LOAD_LOCK = Lock()

MODEL_REPOSITORY = 'Xenova/all-MiniLM-L6-v2'
MODEL_REVISION = '751bff37182d3f1213fa05d7196b954e230abad9'
MODEL_FILES = {
    'model_quantized.onnx': {
        'path': 'onnx/model_quantized.onnx',
        'size': 22972370,
        'sha256': 'afdb6f1a0e45b715d0bb9b11772f032c399babd23bfc31fed1c170afc848bdb1',
    },
    'tokenizer.json': {
        'path': 'tokenizer.json',
        'size': 711661,
        'git_sha1': 'c17ed520ed8438736732a54957a69306b8822215',
    },
}
MAX_SEQUENCE_LENGTH = 256
MAX_PROFILE_CHUNKS = 32
MAX_CRITERIA_PER_GROUP = 12


class SemanticMatchingUnavailable(Exception):
    """Raised when the locally executed pre-trained model cannot be loaded."""


def _file_matches(path, metadata):
    try:
        if path.stat().st_size != metadata['size']:
            return False
        digest = hashlib.sha256() if 'sha256' in metadata else hashlib.sha1()
        if 'git_sha1' in metadata:
            digest.update(f"blob {metadata['size']}\0".encode('ascii'))
        with path.open('rb') as model_file:
            for block in iter(lambda: model_file.read(1024 * 1024), b''):
                digest.update(block)
        expected_hash = metadata.get('sha256', metadata.get('git_sha1'))
        return digest.hexdigest() == expected_hash
    except OSError:
        return False


def _download_model_file(filename, metadata, cache_directory):
    model_path = cache_directory / filename
    if _file_matches(model_path, metadata):
        return model_path

    model_url = (
        f'https://huggingface.co/{MODEL_REPOSITORY}/resolve/'
        f'{MODEL_REVISION}/{metadata["path"]}'
    )
    temporary_path = None
    try:
        with requests.get(
            model_url,
            stream=True,
            timeout=(10, 45),
        ) as response:
            response.raise_for_status()
            with tempfile.NamedTemporaryFile(
                mode='wb',
                dir=cache_directory,
                prefix='.model-download-',
                delete=False,
            ) as temporary_file:
                temporary_path = Path(temporary_file.name)
                downloaded_size = 0
                for block in response.iter_content(chunk_size=1024 * 1024):
                    if block:
                        downloaded_size += len(block)
                        if downloaded_size > metadata['size']:
                            raise SemanticMatchingUnavailable(
                                f'The downloaded pre-trained model file {filename} exceeded its expected size.'
                            )
                        temporary_file.write(block)
        if not _file_matches(temporary_path, metadata):
            raise SemanticMatchingUnavailable(
                f'The downloaded pre-trained model file {filename} failed its integrity check.'
            )
        os.replace(temporary_path, model_path)
        return model_path
    except requests.RequestException as exc:
        raise SemanticMatchingUnavailable(
            'Could not download the pre-trained semantic model. Check outbound access to Hugging Face.'
        ) from exc
    except OSError as exc:
        raise SemanticMatchingUnavailable(
            'Could not cache the pre-trained semantic model in temporary storage.'
        ) from exc
    finally:
        if temporary_path and temporary_path.exists():
            temporary_path.unlink()


def _load_model_runtime():
    global _MODEL_LOAD_FAILURE
    with _MODEL_LOAD_LOCK:
        if _MODEL_LOAD_FAILURE:
            raise _MODEL_LOAD_FAILURE

        try:
            return _initialize_model_runtime()
        except SemanticMatchingUnavailable as exc:
            _MODEL_LOAD_FAILURE = exc
            logger.exception('Pre-trained semantic job-matching model is unavailable.')
            raise


@lru_cache(maxsize=1)
def _initialize_model_runtime():
    cache_directory = Path(tempfile.gettempdir()) / 'multibiz-semantic-model' / MODEL_REVISION
    try:
        cache_directory.mkdir(parents=True, exist_ok=True)
    except OSError as exc:
        raise SemanticMatchingUnavailable(
            'Could not create temporary storage for the pre-trained semantic model.'
        ) from exc

    model_path = _download_model_file(
        'model_quantized.onnx',
        MODEL_FILES['model_quantized.onnx'],
        cache_directory,
    )
    tokenizer_path = _download_model_file(
        'tokenizer.json',
        MODEL_FILES['tokenizer.json'],
        cache_directory,
    )

    try:
        tokenizer = Tokenizer.from_file(str(tokenizer_path))
        tokenizer.enable_truncation(max_length=MAX_SEQUENCE_LENGTH)
        options = onnxruntime.SessionOptions()
        options.intra_op_num_threads = 1
        options.inter_op_num_threads = 1
        session = onnxruntime.InferenceSession(
            str(model_path),
            sess_options=options,
            providers=['CPUExecutionProvider'],
        )
    except (OSError, RuntimeError, ValueError) as exc:
        raise SemanticMatchingUnavailable(
            'The pre-trained semantic model or tokenizer could not be initialized.'
        ) from exc

    return tokenizer, session


def _text_chunks(text, maximum=MAX_PROFILE_CHUNKS):
    sentences = [
        sentence.strip()
        for sentence in re.split(r'(?<=[.!?])\s+|\n+', text or '')
        if sentence.strip()
    ]
    chunks = []
    for sentence in sentences:
        while len(sentence) > 1200:
            split_at = sentence.rfind(' ', 0, 1200)
            split_at = split_at if split_at > 0 else 1200
            chunks.append(sentence[:split_at].strip())
            sentence = sentence[split_at:].strip()
        if sentence:
            chunks.append(sentence)
    if len(chunks) > maximum:
        step = (len(chunks) - 1) / (maximum - 1)
        chunks = [chunks[round(index * step)] for index in range(maximum)]
    return chunks


def _embeddings(texts, tokenizer, session):
    encodings = [
        tokenizer.encode(text, add_special_tokens=True)
        for text in texts
    ]
    width = max(len(encoding.ids) for encoding in encodings)
    pad_id = tokenizer.token_to_id('[PAD]')
    pad_id = pad_id if pad_id is not None else 0
    input_ids = np.full((len(encodings), width), pad_id, dtype=np.int64)
    attention_mask = np.zeros((len(encodings), width), dtype=np.int64)
    token_type_ids = np.zeros((len(encodings), width), dtype=np.int64)

    for index, encoding in enumerate(encodings):
        length = len(encoding.ids)
        input_ids[index, :length] = encoding.ids
        attention_mask[index, :length] = encoding.attention_mask
        token_type_ids[index, :length] = encoding.type_ids

    available_inputs = {item.name for item in session.get_inputs()}
    inputs = {
        'input_ids': input_ids,
        'attention_mask': attention_mask,
    }
    if 'token_type_ids' in available_inputs:
        inputs['token_type_ids'] = token_type_ids
    try:
        output = session.run(None, inputs)[0]
    except (RuntimeError, ValueError) as exc:
        raise SemanticMatchingUnavailable(
            'The pre-trained semantic model could not process this profile and job.'
        ) from exc
    mask = attention_mask[:, :, np.newaxis].astype(output.dtype)
    pooled = (output * mask).sum(axis=1) / np.maximum(mask.sum(axis=1), 1e-9)
    norms = np.linalg.norm(pooled, axis=1, keepdims=True)
    return pooled / np.maximum(norms, 1e-12)


def _cosine_relevance(criterion_embeddings, profile_embeddings):
    similarities = criterion_embeddings @ profile_embeddings.T
    best_matches = similarities.max(axis=1)
    return float(np.clip(best_matches.mean(), 0.0, 1.0))


def _criteria_groups(job):
    groups = []
    role_text = ' '.join(
        value.strip()
        for value in (job.title, job.requirements, job.description)
        if value and value.strip()
    )
    role_chunks = _text_chunks(role_text, MAX_CRITERIA_PER_GROUP)
    if role_chunks:
        groups.append(('Role and requirements', role_chunks))

    skills = [
        skill.strip()
        for skill in (job.skills_required or '').split(',')
        if skill.strip()
    ][:MAX_CRITERIA_PER_GROUP]
    if skills:
        groups.append(('Required skills', skills))

    qualifications = [
        mapping.qualification.name.strip()
        for mapping in job.qualification_mappings.select_related('qualification').all()
        if mapping.qualification and mapping.qualification.name.strip()
    ][:MAX_CRITERIA_PER_GROUP]
    if qualifications:
        groups.append(('Qualifications', qualifications))

    return groups


def compute_semantic_match(
    applicant: Applicant,
    job: JobPosting,
    resume_text: str = '',
) -> dict[str, object]:
    """Use a pinned, local sentence-transformer model to estimate semantic relevance."""
    experience = max(0, int(applicant.experience_years or 0))
    profile_text = ' '.join(
        value.strip()
        for value in (
            applicant.skills,
            applicant.qualifications,
            applicant.education_level,
            f'{experience} years of experience' if experience else '',
            resume_text,
        )
        if value and value.strip()
    )
    profile_chunks = _text_chunks(profile_text)
    if not profile_chunks:
        return {
            'score': None,
            'reason': 'Add skills, qualifications, experience, or a resume to your profile to get an AI match estimate.',
            'breakdown': [],
        }

    groups = _criteria_groups(job)
    if not groups:
        return {
            'score': None,
            'reason': 'This job does not have enough role information to calculate a semantic match.',
            'breakdown': [],
        }

    tokenizer, session = _load_model_runtime()
    profile_embeddings = _embeddings(profile_chunks, tokenizer, session)
    breakdown = []
    for label, criteria in groups:
        criteria_embeddings = _embeddings(criteria, tokenizer, session)
        group_score = _cosine_relevance(criteria_embeddings, profile_embeddings)
        breakdown.append({
            'label': label,
            'score': round(group_score * 100, 1),
        })

    score = sum(item['score'] for item in breakdown) / len(breakdown)
    return {
        'score': round(score, 1),
        'reason': '',
        'breakdown': breakdown,
    }
