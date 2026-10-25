PUBLIC_PAGE_VIEW_NAMES = frozenset({
    'about_view',
    'audiovisual_view',
    'businessautomation_view',
    'careers_view',
    'computingproducts_view',
    'contactus_view',
    'cybersecurity_view',
    'hp_view',
    'index_view',
    'informationmanagement_view',
    'job_details_public_view',
    'news_details_view',
    'news_view',
    'printmanagement_view',
    'retailsolutions_view',
    'services_view',
    'solutions_view',
})


def is_public_page_view(view_func):
    return (
        getattr(view_func, '__module__', '') == 'app.views.views_public'
        and getattr(view_func, '__name__', '') in PUBLIC_PAGE_VIEW_NAMES
    )
