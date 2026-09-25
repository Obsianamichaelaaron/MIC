// Brand data for the modal
const brandData = {
    hp: {
        name: "HP Inc.",
        description: "HP Inc. is a global leader in printing and personal computing solutions. With a rich history of innovation, HP delivers products and services that empower businesses and individuals to achieve more.",
        features: [
            "Industry-leading printers and printing solutions",
            "High-performance computing devices and workstations",
            "Managed print services and supplies",
            "3D printing solutions",
            "Security and device management"
        ],
        partnership: "Multibiz has been an HP Gold Partner for over 15 years, providing expert consultation, implementation, and support for HP products across various industries."
    },
    microsoft: {
        name: "Microsoft",
        description: "Microsoft Corporation is an American multinational technology company that develops, manufactures, licenses, supports, and sells computer software, consumer electronics, personal computers, and related services.",
        features: [
            "Windows operating systems",
            "Microsoft 365 productivity suite",
            "Azure cloud services",
            "Enterprise software solutions",
            "Collaboration tools including Teams"
        ],
        partnership: "As a Microsoft Silver Partner, Multibiz helps businesses transform their operations with Microsoft's powerful software and cloud solutions."
    },
    cisco: {
        name: "Cisco Systems",
        description: "Cisco Systems, Inc. is an American multinational technology conglomerate that develops, manufactures and sells networking hardware, software, telecommunications equipment and other high-technology services and products.",
        features: [
            "Networking infrastructure and hardware",
            "Cybersecurity solutions",
            "Collaboration tools including Webex",
            "Data center solutions",
            "Internet of Things (IoT) platforms"
        ],
        partnership: "Multibiz partners with Cisco to deliver secure, reliable networking solutions that form the backbone of modern business operations."
    },
    sap: {
        name: "SAP SE",
        description: "SAP SE is a German multinational software corporation that makes enterprise software to manage business operations and customer relations.",
        features: [
            "ERP (Enterprise Resource Planning) systems",
            "Business intelligence and analytics",
            "Supply chain management",
            "Human capital management",
            "Customer relationship management"
        ],
        partnership: "As an SAP solution provider, Multibiz implements and supports SAP systems that help businesses streamline operations and make data-driven decisions."
    },
    canon: {
        name: "Canon Inc.",
        description: "Canon Inc. is a Japanese multinational corporation specializing in the manufacture of imaging and optical products, including cameras, photocopiers, steppers, computer printers and medical equipment.",
        features: [
            "Digital cameras and lenses",
            "Office multifunction printers",
            "Production printing solutions",
            "Professional video equipment",
            "Medical imaging systems"
        ],
        partnership: "Multibiz distributes Canon's industry-leading imaging solutions, helping businesses improve document workflows and visual communication."
    },
    dell: {
        name: "Dell Technologies",
        description: "Dell Technologies is an American multinational technology company that develops, sells, repairs, and supports computers and related products and services.",
        features: [
            "Desktop and laptop computers",
            "Servers and storage solutions",
            "Networking equipment",
            "Monitors and peripherals",
            "Support and deployment services"
        ],
        partnership: "As a Dell Preferred Partner, Multibiz provides comprehensive Dell technology solutions with expert implementation and support services."
    }
};

// Page-specific functionality moved to separate files:
// - landingPage.js (initHeroSlider, initNewsSlider)
// - about.js (initAboutPage)
// - services.js (initServicesPage)

// Enhanced Scroll Animation
function initScrollAnimations() {
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    
    function checkScroll() {
        const triggerBottom = window.innerHeight * 0.8;
        
        animateElements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;
            
            if (elementTop < triggerBottom) {
                element.classList.add('animated');
            }
        });
    }
    
    // Use Intersection Observer for better performance
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        animateElements.forEach(element => {
            observer.observe(element);
        });
    } else {
        // Fallback for older browsers
        window.addEventListener('scroll', checkScroll);
        checkScroll();
    }
}

// Enhanced Form Handling
function initForms() {
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        // Real-time validation
        const inputs = contactForm.querySelectorAll('input, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
        
        // Form submission
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Always proceed with submission - HTML5 validation handles required fields
            console.log('Contact form submitted');
            
            // Show loading state
            const submitBtn = contactForm.querySelector('.form-submit');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;
            
            // Hide any previous messages
            const messageDiv = document.getElementById('contactFormMessage');
            if (!messageDiv) {
                console.error('Contact form message div not found!');
                return;
            }
            messageDiv.style.display = 'none';
            
            // Get form data
            const formData = new FormData(contactForm);
            
            // Log form data for debugging
            console.log('Form data:', {
                name: formData.get('name'),
                email: formData.get('email'),
                message: formData.get('message')
            });
            
            // Submit to server
            const handlerUrl = 'includes/handlers/contact_handler.php';
            console.log('Submitting to:', handlerUrl);
            
            fetch(handlerUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Contact form response:', data);
                if (data.success) {
                    messageDiv.style.display = 'block';
                    messageDiv.style.background = '#d4edda';
                    messageDiv.style.color = '#155724';
                    messageDiv.style.border = '1px solid #c3e6cb';
                    messageDiv.textContent = data.message;
                    contactForm.reset();
                } else {
                    messageDiv.style.display = 'block';
                    messageDiv.style.background = '#f8d7da';
                    messageDiv.style.color = '#721c24';
                    messageDiv.style.border = '1px solid #f5c6cb';
                    messageDiv.textContent = data.errors ? data.errors.join(', ') : 'An error occurred. Please try again.';
                }
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                console.error('Contact form error:', error);
                messageDiv.style.display = 'block';
                messageDiv.style.background = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.textContent = 'An error occurred. Please try again. Error: ' + error.message;
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

// Form validation functions
function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    
    clearFieldError(field);
    
    if (field.type === 'email' && value && !validateEmail(value)) {
        showError(field, 'Please enter a valid email address');
        return false;
    }
    
    if (field.required && !value) {
        showError(field, 'This field is required');
        return false;
    }
    
    return true;
}

function validateForm() {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return true;
    
    const inputs = contactForm.querySelectorAll('input[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField({ target: input })) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showError(input, message) {
    const formGroup = input.closest('.form-group');
    let errorElement = formGroup.querySelector('.error-message');
    
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        formGroup.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
    input.classList.add('error');
}

function clearFieldError(e) {
    const input = e.target || e;
    const formGroup = input.closest('.form-group');
    const errorElement = formGroup?.querySelector('.error-message');
    
    if (errorElement) {
        errorElement.remove();
    }
    
    input.classList.remove('error');
}

// Google Sign-In Functionality
function initGoogleSignIn() {
    const googleSignInBtn = document.getElementById('googleSignIn');
    const userInfo = document.getElementById('userInfo');
    const signOutBtn = document.getElementById('signOut');
    
    if (googleSignInBtn) {
        googleSignInBtn.addEventListener('click', function() {
            // Simulate Google Sign-In
            simulateGoogleSignIn();
        });
    }
    
    if (signOutBtn) {
        signOutBtn.addEventListener('click', function() {
            // Simulate Sign Out
            simulateSignOut();
        });
    }
    
    function simulateGoogleSignIn() {
        const userInfo = document.getElementById('userInfo');
        const userName = document.getElementById('userName');
        const userEmail = document.getElementById('userEmail');
        const userPhoto = document.getElementById('userPhoto');
        const googleSignInBtn = document.getElementById('googleSignIn');
        
        // Simulate user data
        userName.textContent = 'John Doe';
        userEmail.textContent = 'john.doe@example.com';
        userPhoto.src = 'https://placehold.co/100x100/0056b3/white?text=JD';
        
        // Show user info and hide sign-in button
        userInfo.style.display = 'block';
        googleSignInBtn.style.display = 'none';
        
        // Add success state
        googleSignInBtn.classList.add('success');
    }
    
    function simulateSignOut() {
        const userInfo = document.getElementById('userInfo');
        const googleSignInBtn = document.getElementById('googleSignIn');
        
        // Hide user info and show sign-in button
        userInfo.style.display = 'none';
        googleSignInBtn.style.display = 'flex';
        
        // Remove success state
        googleSignInBtn.classList.remove('success');
    }
}

// Performance Optimizations
function initPerformance() {
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Debounce scroll events
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            // Scroll-based actions here
        }, 100);
    });
}

// Error handling and logging
function initErrorHandling() {
    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('Error occurred:', e.error);
        
        if (e.target.tagName === 'IMG') {
            console.log('Image failed to load:', e.target.src);
            // Set placeholder image
            e.target.src = 'https://placehold.co/400x300/0056b3/white?text=Image+Not+Found';
            e.target.alt = 'Image not available';
        }
    });
    
    // Promise rejection handler
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
    });
}

// Main DOM Content Loaded Function - Common functionality only
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - script.js is working!');
    
    // Initialize common/shared functionality
    initScrollAnimations();
    initForms();
    initGoogleSignIn();
    initPerformance();
    initErrorHandling();
    initMobileNavigation();
    initHeaderEffects();
    initTestimonials();
    initBrandFiltering();
    initBrandModal();
});

// Common Function Modules
function initMobileNavigation() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
            
            const icon = navToggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });
    }
    
    // Mobile Dropdown Toggle
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        if (item.querySelector('.dropdown')) {
            item.addEventListener('click', function(e) {
                if (window.innerWidth < 992) {
                    if (e.target.classList.contains('nav-link') || e.target.parentElement.classList.contains('nav-link')) {
                        e.preventDefault();
                        this.classList.toggle('active');
                    }
                }
            });
        }
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (navMenu && navMenu.classList.contains('active') && 
            !navMenu.contains(e.target) && 
            !navToggle.contains(e.target)) {
            navMenu.classList.remove('active');
            document.body.style.overflow = '';
            
            const icon = navToggle?.querySelector('i');
            if (icon) {
                icon.classList.add('fa-bars');
                icon.classList.remove('fa-times');
            }
        }
    });
}

function initHeaderEffects() {
    const header = document.querySelector('header');
    
    if (header) {
        let lastScrollY = window.scrollY;
        
        window.addEventListener('scroll', function() {
            // Scroll effect
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            // Hide/show header on scroll
            if (window.scrollY > lastScrollY && window.scrollY > 100) {
                header.style.transform = 'translateY(-100%)';
            } else {
                header.style.transform = 'translateY(0)';
            }
            
            lastScrollY = window.scrollY;
        });
    }
}

function initTestimonials() {
    const testimonialItems = document.querySelectorAll('.testimonial-item');
    const testimonialDots = document.querySelectorAll('.testimonial-dot');
    
    if (testimonialItems.length === 0) return;
    
    let currentTestimonial = 0;
    let testimonialInterval;
    
    function showTestimonial(n) {
        testimonialItems.forEach(item => item.classList.remove('active'));
        testimonialDots.forEach(dot => dot.classList.remove('active'));
        
        if (testimonialItems[n]) {
            testimonialItems[n].classList.add('active');
        }
        if (testimonialDots[n]) {
            testimonialDots[n].classList.add('active');
        }
        currentTestimonial = n;
    }
    
    function nextTestimonial() {
        currentTestimonial = (currentTestimonial + 1) % testimonialItems.length;
        showTestimonial(currentTestimonial);
    }
    
    testimonialDots.forEach(dot => {
        dot.addEventListener('click', function() {
            const slideIndex = parseInt(this.getAttribute('data-slide'));
            showTestimonial(slideIndex);
            resetTestimonialInterval();
        });
    });
    
    function startTestimonialInterval() {
        testimonialInterval = setInterval(nextTestimonial, 5000);
    }
    
    function resetTestimonialInterval() {
        clearInterval(testimonialInterval);
        startTestimonialInterval();
    }
    
    // Pause on hover
    const testimonialSlider = document.querySelector('.testimonial-slider');
    if (testimonialSlider) {
        testimonialSlider.addEventListener('mouseenter', () => {
            clearInterval(testimonialInterval);
        });
        
        testimonialSlider.addEventListener('mouseleave', () => {
            startTestimonialInterval();
        });
    }
    
    // Initialize
    showTestimonial(0);
    startTestimonialInterval();
}

function initBrandFiltering() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const brandCards = document.querySelectorAll('.brand-card');
    
    if (filterButtons.length === 0) return;
    
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            button.classList.add('active');
            
            const filterValue = button.getAttribute('data-filter');
            
            // Filter brand cards with animation
            brandCards.forEach(card => {
                if (filterValue === 'all') {
                    card.style.display = 'block';
                    setTimeout(() => card.style.opacity = '1', 50);
                } else {
                    const categories = card.getAttribute('data-category').split(',');
                    if (categories.includes(filterValue)) {
                        card.style.display = 'block';
                        setTimeout(() => card.style.opacity = '1', 50);
                    } else {
                        card.style.opacity = '0';
                        setTimeout(() => card.style.display = 'none', 300);
                    }
                }
            });
        });
    });
}

function initBrandModal() {
    const brandModal = document.getElementById('brandModal');
    const modalClose = document.querySelector('.modal-close');
    const viewDetailsButtons = document.querySelectorAll('.view-details');
    
    if (!brandModal) return;
    
    function openBrandModal(brandKey) {
        const brand = brandData[brandKey];
        if (!brand) return;
        
        // Set modal title
        const modalBrandName = document.getElementById('modalBrandName');
        if (modalBrandName) {
            modalBrandName.textContent = brand.name;
        }
        
        // Build modal content
        const modalContent = document.getElementById('modalBrandContent');
        if (modalContent) {
            modalContent.innerHTML = `
                <div class="modal-brand-info">
                    <div class="modal-brand-logo">
                        <img src="https://placehold.co/200x100/0056b3/white?text=${encodeURIComponent(brand.name)}" alt="${brand.name}">
                    </div>
                    <div class="modal-brand-details">
                        <h3>${brand.name}</h3>
                        <p>${brand.description}</p>
                        <p><strong>Partnership:</strong> ${brand.partnership}</p>
                    </div>
                </div>
                <div class="modal-features">
                    <h3>Key Solutions</h3>
                    <div class="features-grid">
                        ${brand.features.map(feature => `
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="feature-text">${feature}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        // Show modal
        brandModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Add escape key listener
        document.addEventListener('keydown', handleEscapeKey);
    }
    
    function closeBrandModal() {
        brandModal.classList.remove('active');
        document.body.style.overflow = 'auto';
        document.removeEventListener('keydown', handleEscapeKey);
    }
    
    function handleEscapeKey(e) {
        if (e.key === 'Escape') {
            closeBrandModal();
        }
    }
    
    // Event listeners for modal
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const brandKey = button.getAttribute('data-brand');
            openBrandModal(brandKey);
        });
    });
    
    if (modalClose) {
        modalClose.addEventListener('click', closeBrandModal);
    }
    
    // Close modal when clicking outside
    brandModal.addEventListener('click', (e) => {
        if (e.target === brandModal) {
            closeBrandModal();
        }
    });
}

// Utility Functions
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func(...args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func(...args);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, pause any intensive animations
        document.body.classList.add('page-hidden');
    } else {
        // Page is visible, resume animations
        document.body.classList.remove('page-hidden');
    }
});

// Update current year in footer
function updateCurrentYear() {
    const currentYearElements = document.querySelectorAll('#currentYear');
    const currentYear = new Date().getFullYear();
    
    currentYearElements.forEach(element => {
        element.textContent = currentYear;
    });
}

// Initialize when DOM is fully loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateCurrentYear);
} else {
    updateCurrentYear();
}

