import hashlib
import json
import logging
import os
import re
import tempfile
import unicodedata
from dataclasses import dataclass
from threading import Lock
from functools import lru_cache
from pathlib import Path

import numpy as np
import onnxruntime
import requests

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


@dataclass(frozen=True)
class _BertWordPieceTokenizer:
    vocabulary: dict[str, int]
    unknown_token: str
    continuing_subword_prefix: str
    max_input_chars_per_word: int
    special_token_ids: dict[str, int]
    special_token_pattern: re.Pattern
    pad_token_id: int
    padding_length: int
    max_length: int
    lowercase: bool
    strip_accents: bool

    def _normalize(self, text):
        cleaned = []
        for char in text:
            category = unicodedata.category(char)
            if char in ('\x00', '\ufffd') or (
                category.startswith('C') and char not in '\t\n\r'
            ):
                continue
            if char in ' \t\n\r' or category == 'Zs':
                cleaned.append(' ')
                continue
            if self._is_chinese_character(char):
                cleaned.extend((' ', char, ' '))
                continue
            cleaned.append(char)

        normalized = ''.join(cleaned)
        if self.lowercase:
            normalized = normalized.lower()
        if self.strip_accents:
            normalized = ''.join(
                char
                for char in unicodedata.normalize('NFD', normalized)
                if unicodedata.category(char) != 'Mn'
            )
        return normalized

    @staticmethod
    def _is_chinese_character(char):
        codepoint = ord(char)
        return (
            0x4E00 <= codepoint <= 0x9FFF
            or 0x3400 <= codepoint <= 0x4DBF
            or 0x20000 <= codepoint <= 0x2A6DF
            or 0x2A700 <= codepoint <= 0x2B73F
            or 0x2B740 <= codepoint <= 0x2B81F
            or 0x2B820 <= codepoint <= 0x2CEAF
            or 0xF900 <= codepoint <= 0xFAFF
            or 0x2F800 <= codepoint <= 0x2FA1F
        )

    @staticmethod
    def _is_punctuation(char):
        codepoint = ord(char)
        return (
            33 <= codepoint <= 47
            or 58 <= codepoint <= 64
            or 91 <= codepoint <= 96
            or 123 <= codepoint <= 126
            or unicodedata.category(char).startswith('P')
        )

    def _wordpiece(self, word):
        if len(word) > self.max_input_chars_per_word:
            return [self.unknown_token]

        tokens = []
        start = 0
        while start < len(word):
            end = len(word)
            found = None
            while start < end:
                piece = word[start:end]
                if start:
                    piece = self.continuing_subword_prefix + piece
                if piece in self.vocabulary:
                    found = piece
                    break
                end -= 1
            if found is None:
                return [self.unknown_token]
            tokens.append(found)
            start = end
        return tokens

    def encode(self, text):
        tokens = []
        for segment in self.special_token_pattern.split(text):
            if not segment:
                continue
            if segment in self.special_token_ids:
                tokens.append(segment)
                continue

            normalized = self._normalize(segment)
            words = []
            current = []
            for char in normalized:
                category = unicodedata.category(char)
                if char in ' \t\n\r' or category == 'Zs':
                    if current:
                        words.append(''.join(current))
                        current = []
                elif self._is_punctuation(char):
                    if current:
                        words.append(''.join(current))
                        current = []
                    words.append(char)
                else:
                    current.append(char)
            if current:
                words.append(''.join(current))

            for word in words:
                tokens.extend(self._wordpiece(word))

        cls_id = self.vocabulary['[CLS]']
        sep_id = self.vocabulary['[SEP]']
        ids = [cls_id]
        unknown_id = self.vocabulary[self.unknown_token]
        ids.extend(
            self.special_token_ids.get(token, self.vocabulary.get(token, unknown_id))
            for token in tokens
        )
        ids.append(sep_id)
        if len(ids) > self.max_length:
            ids = ids[:self.max_length - 1] + [sep_id]

        active_length = len(ids)
        padded_length = max(self.padding_length, active_length)
        attention_mask = [1] * active_length + [0] * (padded_length - active_length)
        ids.extend([self.pad_token_id] * (padded_length - active_length))
        return ids, attention_mask


def _load_wordpiece_tokenizer(tokenizer_path):
    data = json.loads(tokenizer_path.read_text(encoding='utf-8'))
    model = data.get('model') or {}
    normalizer = data.get('normalizer') or {}
    pre_tokenizer = data.get('pre_tokenizer') or {}
    padding = data.get('padding') or {}
    padding_strategy = padding.get('strategy') or {}

    if (
        model.get('type') != 'WordPiece'
        or normalizer.get('type') != 'BertNormalizer'
        or pre_tokenizer.get('type') != 'BertPreTokenizer'
        or normalizer.get('clean_text') is not True
        or normalizer.get('handle_chinese_chars') is not True
        or padding.get('direction') != 'Right'
        or not isinstance(padding_strategy.get('Fixed'), int)
    ):
        raise ValueError('The downloaded tokenizer configuration is not a supported BERT WordPiece tokenizer.')

    vocabulary = model.get('vocab')
    if not isinstance(vocabulary, dict):
        raise ValueError('The downloaded tokenizer vocabulary is invalid.')

    special_token_ids = {
        item['content']: item['id']
        for item in data.get('added_tokens', [])
        if item.get('special') is True
        and item.get('normalized') is False
        and item.get('content') in vocabulary
    }
    required_tokens = ('[UNK]', '[CLS]', '[SEP]', '[PAD]')
    if any(token not in vocabulary for token in required_tokens):
        raise ValueError('The downloaded tokenizer is missing a required BERT special token.')
    if any(token not in special_token_ids for token in required_tokens):
        raise ValueError('The downloaded tokenizer special-token metadata is invalid.')

    unknown_token = model.get('unk_token')
    if unknown_token not in vocabulary:
        raise ValueError('The downloaded tokenizer unknown token is invalid.')
    special_tokens = sorted(special_token_ids, key=len, reverse=True)
    special_token_pattern = re.compile(
        '(' + '|'.join(re.escape(token) for token in special_tokens) + ')'
    )
    lowercase = normalizer.get('lowercase') is True
    strip_accents = normalizer.get('strip_accents')
    if strip_accents is None:
        strip_accents = lowercase

    return _BertWordPieceTokenizer(
        vocabulary=vocabulary,
        unknown_token=unknown_token,
        continuing_subword_prefix=model.get('continuing_subword_prefix', '##'),
        max_input_chars_per_word=model.get('max_input_chars_per_word', 100),
        special_token_ids=special_token_ids,
        special_token_pattern=special_token_pattern,
        pad_token_id=padding.get('pad_id', vocabulary['[PAD]']),
        padding_length=padding_strategy['Fixed'],
        max_length=MAX_SEQUENCE_LENGTH,
        lowercase=lowercase,
        strip_accents=strip_accents is True,
    )


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
        tokenizer = _load_wordpiece_tokenizer(tokenizer_path)
        options = onnxruntime.SessionOptions()
        options.intra_op_num_threads = 1
        options.inter_op_num_threads = 1
        session = onnxruntime.InferenceSession(
            str(model_path),
            sess_options=options,
            providers=['CPUExecutionProvider'],
        )
    except (KeyError, OSError, RuntimeError, TypeError, ValueError) as exc:
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
    encodings = [tokenizer.encode(text) for text in texts]
    width = max(len(ids) for ids, _ in encodings)
    input_ids = np.full((len(encodings), width), tokenizer.pad_token_id, dtype=np.int64)
    attention_mask = np.zeros((len(encodings), width), dtype=np.int64)
    token_type_ids = np.zeros((len(encodings), width), dtype=np.int64)

    for index, (ids, mask) in enumerate(encodings):
        length = len(ids)
        input_ids[index, :length] = ids
        attention_mask[index, :length] = mask

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
