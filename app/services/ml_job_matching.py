import hashlib
import json
import re
from functools import lru_cache

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import (
    accuracy_score,
    cohen_kappa_score,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
)
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.model_selection import StratifiedGroupKFold
from sklearn.pipeline import Pipeline
from sklearn.linear_model import LogisticRegression

from django.db.models import OuterRef, Subquery

from app.models import Application, ResumeAnalysis


MATCH_CLASSES = ('high', 'medium', 'low')
REVIEW_LABELS = {
    'qualified': 'high',
    'under_qualified': 'medium',
    'not_qualified': 'low',
}
MIN_SAMPLES_PER_CLASS = 2
RANDOM_STATE = 42


def build_match_text(applicant, job, resume_text=''):
    parts = (
        resume_text,
        job.title,
        job.description,
        job.requirements,
        job.skills_required,
        applicant.skills,
        applicant.qualifications,
        applicant.education_level,
        f'{applicant.experience_years or 0} years experience',
    )
    return ' '.join(str(part).strip() for part in parts if part and str(part).strip())


def estimate_job_text_similarity(applicant, job, resume_text=''):
    """Return TF-IDF text similarity, not a trained match probability."""
    applicant_parts = (
        resume_text,
        applicant.skills,
        applicant.qualifications,
        applicant.education_level,
        (
            f'{applicant.experience_years} years experience'
            if applicant.experience_years else ''
        ),
    )
    applicant_text = ' '.join(
        str(part).strip()
        for part in applicant_parts
        if part and str(part).strip()
    )
    job_text = ' '.join(
        str(part).strip()
        for part in (
            job.title,
            job.description,
            job.requirements,
            job.skills_required,
        )
        if part and str(part).strip()
    )
    if not applicant_text.strip() or not job_text.strip():
        return None

    vectorizer = TfidfVectorizer(
        lowercase=True,
        strip_accents='unicode',
        ngram_range=(1, 2),
        max_features=30000,
        sublinear_tf=True,
    )
    vectors = vectorizer.fit_transform([applicant_text, job_text])
    return round(float(cosine_similarity(vectors[0], vectors[1])[0, 0]) * 100, 2)


def load_reviewed_match_samples():
    latest_resume_text = ResumeAnalysis.objects.filter(
        applicant_id=OuterRef('applicant_id')
    ).order_by('-analysis_date').values('extracted_text')[:1]
    applications = Application.objects.filter(
        admin_qualification__in=REVIEW_LABELS
    ).select_related('applicant', 'job').annotate(
        resume_text=Subquery(latest_resume_text)
    ).order_by('application_id')

    return [
        {
            'text': build_match_text(
                application.applicant, application.job, application.resume_text
            ),
            'label': REVIEW_LABELS[application.admin_qualification],
            'applicant_id': application.applicant_id,
        }
        for application in applications
        if application.applicant_id and application.job_id
    ]


def _class_counts(samples):
    return {
        label: sum(1 for sample in samples if sample['label'] == label)
        for label in MATCH_CLASSES
    }


def _model_readiness(samples, minimum_per_class=MIN_SAMPLES_PER_CLASS):
    counts = _class_counts(samples)
    if any(sample['label'] not in MATCH_CLASSES for sample in samples):
        return counts, 'Training data contains a label outside the required high, medium, low classes.'
    missing = [label for label, count in counts.items() if count < minimum_per_class]
    if missing:
        minimum_words = {1: 'one', 2: 'two', 3: 'three'}
        minimum_text = minimum_words.get(minimum_per_class, str(minimum_per_class))
        return counts, (
            f'Match classes need at least {minimum_text} admin-reviewed examples each. '
            f'Current counts: high {counts["high"]}, medium {counts["medium"]}, '
            f'low {counts["low"]}.'
        )
    if any(not sample['text'].strip() for sample in samples):
        return counts, 'Training is unavailable because a reviewed application has no match text.'
    return counts, ''


def _new_pipeline():
    return Pipeline([
        ('tfidf', TfidfVectorizer(
            lowercase=True,
            strip_accents='unicode',
            ngram_range=(1, 2),
            max_features=30000,
            sublinear_tf=True,
        )),
        ('classifier', LogisticRegression(
            class_weight='balanced',
            max_iter=1000,
            random_state=RANDOM_STATE,
        )),
    ])


def train_match_classifier(samples, minimum_per_class=MIN_SAMPLES_PER_CLASS):
    samples = list(samples)
    counts, reason = _model_readiness(samples, minimum_per_class)
    if reason:
        return None, {'ready': False, 'reason': reason, 'class_counts': counts}

    model = _new_pipeline()
    try:
        model.fit(
            [sample['text'] for sample in samples],
            [sample['label'] for sample in samples],
        )
    except ValueError as exc:
        return None, {
            'ready': False,
            'reason': f'TF-IDF could not fit the reviewed match text: {exc}',
            'class_counts': counts,
        }
    return model, {'ready': True, 'reason': '', 'class_counts': counts}


def evaluate_match_model(samples=None):
    samples = load_reviewed_match_samples() if samples is None else list(samples)
    counts, reason = _model_readiness(samples)
    if reason:
        return {
            'ready': False,
            'reason': reason,
            'class_counts': counts,
        }

    texts = [sample['text'] for sample in samples]
    labels = [sample['label'] for sample in samples]
    try:
        groups = [
            sample.get('applicant_id', f'sample-{index}')
            for index, sample in enumerate(samples)
        ]
        train_indices, test_indices = next(
            StratifiedGroupKFold(
                n_splits=2,
                shuffle=True,
                random_state=RANDOM_STATE,
            ).split(texts, labels, groups)
        )
    except ValueError as exc:
        return {
            'ready': False,
            'reason': f'Unable to create a stratified three-class applicant-group split: {exc}',
            'class_counts': counts,
        }

    train_texts = [texts[index] for index in train_indices]
    test_texts = [texts[index] for index in test_indices]
    train_labels = [labels[index] for index in train_indices]
    test_labels = [labels[index] for index in test_indices]
    if set(train_labels) != set(MATCH_CLASSES) or set(test_labels) != set(MATCH_CLASSES):
        return {
            'ready': False,
            'reason': 'Unable to create a holdout split containing all three classes in both sets.',
            'class_counts': counts,
        }

    model, status = train_match_classifier(
        (
            {'text': text, 'label': label}
            for text, label in zip(train_texts, train_labels)
        ),
        minimum_per_class=1,
    )
    if not model:
        return status
    predictions = model.predict(test_texts)
    matrix = confusion_matrix(test_labels, predictions, labels=MATCH_CLASSES)
    confusion_rows = []
    for row_index, label in enumerate(MATCH_CLASSES):
        total = int(matrix[row_index].sum())
        cells = []
        for count in matrix[row_index]:
            value = int(count)
            row_accuracy = value / total if total else 0.0
            cells.append({
                'count': value,
                'row_accuracy': round(row_accuracy * 100, 1),
                'opacity': round(0.12 + row_accuracy * 0.68, 3),
            })
        confusion_rows.append({'label': label, 'cells': cells})

    metrics = {
        'accuracy': accuracy_score(test_labels, predictions),
        'precision': precision_score(
            test_labels, predictions, labels=MATCH_CLASSES, average='macro', zero_division=0
        ),
        'recall': recall_score(
            test_labels, predictions, labels=MATCH_CLASSES, average='macro', zero_division=0
        ),
        'f1': f1_score(
            test_labels, predictions, labels=MATCH_CLASSES, average='macro', zero_division=0
        ),
        'kappa': cohen_kappa_score(test_labels, predictions, labels=MATCH_CLASSES),
    }
    return {
        'ready': True,
        'reason': '',
        'class_counts': counts,
        'training_count': len(train_texts),
        'test_count': len(test_texts),
        **metrics,
        'accuracy_percent': round(metrics['accuracy'] * 100, 1),
        'precision_percent': round(metrics['precision'] * 100, 1),
        'recall_percent': round(metrics['recall'] * 100, 1),
        'f1_percent': round(metrics['f1'] * 100, 1),
        'confusion_rows': confusion_rows,
    }


@lru_cache(maxsize=4)
def _fit_cached_model(dataset_digest, samples):
    samples = [{'text': text, 'label': label} for text, label in samples]
    return train_match_classifier(samples)


def get_match_classifier(exclude_applicant_id=None):
    samples = load_reviewed_match_samples()
    if exclude_applicant_id is not None:
        samples = [
            sample for sample in samples
            if sample['applicant_id'] != exclude_applicant_id
        ]
    counts, reason = _model_readiness(samples)
    if reason:
        return None, {'ready': False, 'reason': reason, 'class_counts': counts}

    signature = hashlib.sha256(json.dumps(
        [(sample['text'], sample['label']) for sample in samples],
        ensure_ascii=False,
        separators=(',', ':'),
    ).encode('utf-8')).hexdigest()
    training_samples = tuple(
        (sample['text'], sample['label']) for sample in samples
    )
    model, status = _fit_cached_model(signature, training_samples)
    return model, status


def predict_match_class(model, applicant, job, resume_text=''):
    text = build_match_text(applicant, job, resume_text)
    if not re.search(r'\w', text, flags=re.UNICODE):
        return None
    return str(model.predict([text])[0])


def predict_match_score(model, applicant, job, resume_text=''):
    """Return the model-estimated probability of the admin-reviewed high class."""
    text = build_match_text(applicant, job, resume_text)
    if not re.search(r'\w', text, flags=re.UNICODE):
        return None

    classes = list(model.classes_)
    if 'high' not in classes:
        return None
    high_probability = model.predict_proba([text])[0][classes.index('high')]
    return round(float(high_probability) * 100.0, 2)
