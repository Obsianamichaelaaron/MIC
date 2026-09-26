// LandingPage.php specific JavaScript

// Interactive Hero Slider
function initHeroSlider() {
    const heroImages = document.querySelectorAll('.hero-image');
    const heroIndicators = document.querySelectorAll('.hero-indicator');
    const heroPrev = document.getElementById('heroPrev');
    const heroNext = document.getElementById('heroNext');
    const heroSection = document.querySelector('.hero');
    
    if (!heroImages || heroImages.length === 0) return;
    
    let currentSlide = 0;
    const totalSlides = heroImages.length;
    let autoSlideInterval = null;
    let isPaused = false;
    
    // Update slide classes
    function updateSlide() {
        heroImages.forEach((img, index) => {
            img.classList.toggle('active', index === currentSlide);
        });
        heroIndicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentSlide);
        });
    }
    
    // Go to specific slide
    function goToSlide(slideIndex) {
        currentSlide = ((slideIndex % totalSlides) + totalSlides) % totalSlides;
        updateSlide();
        resetAutoSlide();
    }
    
    // Next slide
    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        updateSlide();
    }
    
    // Previous slide
    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        updateSlide();
    }
    
    // Auto slide
    function startAutoSlide() {
        if (!isPaused && totalSlides > 1) {
            clearInterval(autoSlideInterval);
            autoSlideInterval = setInterval(nextSlide, 6000);
        }
    }
    
    // Reset auto slide timer
    function resetAutoSlide() {
        clearInterval(autoSlideInterval);
        startAutoSlide();
    }
    
    // Pause auto-slide
    function pauseAutoSlide() {
        isPaused = true;
        clearInterval(autoSlideInterval);
    }
    
    // Resume auto-slide
    function resumeAutoSlide() {
        isPaused = false;
        startAutoSlide();
    }
    
    // Event listeners
    if (heroNext) {
        heroNext.addEventListener('click', (e) => {
            e.preventDefault();
            nextSlide();
            resetAutoSlide();
        });
    }
    
    if (heroPrev) {
        heroPrev.addEventListener('click', (e) => {
            e.preventDefault();
            prevSlide();
            resetAutoSlide();
        });
    }
    
    // Indicator clicks
    heroIndicators.forEach((indicator, index) => {
        indicator.addEventListener('click', (e) => {
            e.preventDefault();
            goToSlide(index);
        });
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') {
            nextSlide();
            resetAutoSlide();
        } else if (e.key === 'ArrowLeft') {
            prevSlide();
            resetAutoSlide();
        }
    });
    
    if (heroSection) {
        heroSection.addEventListener('mouseenter', pauseAutoSlide);
        heroSection.addEventListener('mouseleave', resumeAutoSlide);
    }
    
    // Initialize
    updateSlide();
    startAutoSlide();
}

// Latest News Horizontal Scrolling
function initNewsSlider() {
    const newsTrack = document.getElementById('newsTrack');
    const newsPrev = document.getElementById('newsPrev');
    const newsNext = document.getElementById('newsNext');
    const newsCards = document.querySelectorAll('.news-card');
    
    if (!newsTrack || !newsPrev || !newsNext) return;
    
    let currentPosition = 0;
    const cardWidth = newsCards[0]?.offsetWidth + 30; // card width + gap
    const visibleCards = Math.floor(newsTrack.offsetWidth / cardWidth);
    const maxPosition = (newsCards.length - visibleCards) * cardWidth;
    
    // Update navigation buttons
    function updateNavButtons() {
        newsPrev.disabled = currentPosition === 0;
        newsNext.disabled = currentPosition >= maxPosition;
        
        // Add visual feedback for disabled state
        if (newsPrev.disabled) {
            newsPrev.style.opacity = '0.5';
            newsPrev.style.cursor = 'not-allowed';
        } else {
            newsPrev.style.opacity = '1';
            newsPrev.style.cursor = 'pointer';
        }
        
        if (newsNext.disabled) {
            newsNext.style.opacity = '0.5';
            newsNext.style.cursor = 'not-allowed';
        } else {
            newsNext.style.opacity = '1';
            newsNext.style.cursor = 'pointer';
        }
    }
    
    // Scroll to position
    function scrollToPosition(position) {
        newsTrack.scrollTo({
            left: position,
            behavior: 'smooth'
        });
        currentPosition = position;
        updateNavButtons();
    }
    
    // Next button click
    newsNext.addEventListener('click', () => {
        if (currentPosition < maxPosition) {
            const newPosition = Math.min(currentPosition + (cardWidth * visibleCards), maxPosition);
            scrollToPosition(newPosition);
        }
    });
    
    // Previous button click
    newsPrev.addEventListener('click', () => {
        if (currentPosition > 0) {
            const newPosition = Math.max(currentPosition - (cardWidth * visibleCards), 0);
            scrollToPosition(newPosition);
        }
    });
    
    // Make news cards clickable
    newsCards.forEach(card => {
        card.addEventListener('click', (e) => {
            // Don't trigger if clicking on the read more link
            if (!e.target.closest('.news-link')) {
                const link = card.querySelector('.news-link');
                if (link && link.href) {
                    window.location.href = link.href;
                }
            }
        });
        
        // Add keyboard accessibility
        card.setAttribute('tabindex', '0');
        card.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const link = card.querySelector('.news-link');
                if (link && link.href) {
                    window.location.href = link.href;
                }
            }
        });
        
        // Add hover effects
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });
    });
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            const newCardWidth = newsCards[0]?.offsetWidth + 30;
            const newVisibleCards = Math.floor(newsTrack.offsetWidth / newCardWidth);
            const newMaxPosition = (newsCards.length - newVisibleCards) * newCardWidth;
            
            // Adjust current position if it exceeds new max
            if (currentPosition > newMaxPosition) {
                currentPosition = newMaxPosition;
                newsTrack.scrollTo({
                    left: currentPosition,
                    behavior: 'auto'
                });
            }
            
            updateNavButtons();
        }, 250);
    });
    
    // Initialize navigation buttons
    updateNavButtons();
    
    // Add touch/swipe support for mobile
    let startX;
    let scrollLeft;
    let isDragging = false;
    
    newsTrack.addEventListener('touchstart', (e) => {
        startX = e.touches[0].pageX - newsTrack.offsetLeft;
        scrollLeft = newsTrack.scrollLeft;
        isDragging = true;
    });
    
    newsTrack.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.touches[0].pageX - newsTrack.offsetLeft;
        const walk = (x - startX) * 2;
        newsTrack.scrollLeft = scrollLeft - walk;
    });
    
    newsTrack.addEventListener('touchend', () => {
        isDragging = false;
    });
}

// Initialize LandingPage functionality
document.addEventListener('DOMContentLoaded', function() {
    initHeroSlider();
    initNewsSlider();
    // Initialize contact form if initForms function exists
    if (typeof initForms === 'function') {
        initForms();
    }
});

