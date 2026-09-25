// LandingPage.php specific JavaScript

// Interactive Hero Slider
function initHeroSlider() {
    const heroBackground = document.getElementById('heroBackground');
    const heroIndicators = document.querySelectorAll('.hero-indicator');
    const heroPrev = document.getElementById('heroPrev');
    const heroNext = document.getElementById('heroNext');
    
    if (!heroBackground || !heroPrev || !heroNext) return;
    
    let currentSlide = 0;
    const totalSlides = 3; // Updated to match available images
    let autoSlideInterval;
    let isPaused = false;
    
    // Update slide position
    function updateSlide() {
        const translateX = -currentSlide * 33.333; // 33.333% per slide (for 3 slides)
        heroBackground.style.transform = `translateX(${translateX}%)`;
        
        // Update indicators
        heroIndicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentSlide);
        });
    }
    
    // Go to specific slide
    function goToSlide(slideIndex) {
        currentSlide = slideIndex;
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
        if (!isPaused) {
            autoSlideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
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
    heroNext.addEventListener('click', () => {
        nextSlide();
        resetAutoSlide();
    });
    
    heroPrev.addEventListener('click', () => {
        prevSlide();
        resetAutoSlide();
    });
    
    // Indicator clicks
    heroIndicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
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
        } else if (e.key === ' ') {
            // Spacebar toggles pause/play
            isPaused ? resumeAutoSlide() : pauseAutoSlide();
        }
    });
    
    // Pause auto-slide on hover
    heroBackground.addEventListener('mouseenter', () => {
        pauseAutoSlide();
    });
    
    heroBackground.addEventListener('mouseleave', () => {
        resumeAutoSlide();
    });
    
    // Touch/swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    heroBackground.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        pauseAutoSlide();
    });
    
    heroBackground.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
        setTimeout(resumeAutoSlide, 3000); // Resume after 3 seconds
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swipe left - next slide
                nextSlide();
            } else {
                // Swipe right - previous slide
                prevSlide();
            }
            resetAutoSlide();
        }
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

