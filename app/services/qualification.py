UNDER_QUALIFIED_SCORE_MIN = 40
UNDER_QUALIFIED_SCORE_MAX = 60


def classify_match_score(score):
    score = float(score or 0)
    if score < UNDER_QUALIFIED_SCORE_MIN:
        return 'not_qualified'
    if score <= UNDER_QUALIFIED_SCORE_MAX:
        return 'under_qualified'
    return 'qualified'
