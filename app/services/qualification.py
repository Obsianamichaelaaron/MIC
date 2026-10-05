NOT_QUALIFIED_SCORE_MAX = 30
UNCLASSIFIED_SCORE_MIN = 30
UNCLASSIFIED_SCORE_MAX = 40
UNDER_QUALIFIED_SCORE_MIN = 40
UNDER_QUALIFIED_SCORE_MAX = 60
QUALIFIED_SCORE_MIN = 60


def classify_match_score(score):
    score = float(score or 0)
    if score < NOT_QUALIFIED_SCORE_MAX:
        return 'not_qualified'
    if score < UNCLASSIFIED_SCORE_MAX:
        return 'unclassified'
    if score <= UNDER_QUALIFIED_SCORE_MAX:
        return 'under_qualified'
    return 'qualified'
