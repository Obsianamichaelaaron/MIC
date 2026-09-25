// services.php specific JavaScript

// Services Page Specific Functionality
function initServicesPage() {
    // Smooth scrolling for service navigation
    const serviceNavLinks = document.querySelectorAll('.services-nav-link');
    
    serviceNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 100;
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
                
                // Update active state
                serviceNavLinks.forEach(navLink => navLink.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
    
    // Update active service nav link on scroll
    const serviceSections = document.querySelectorAll('.service-section');
    
    if (serviceNavLinks.length > 0 && serviceSections.length > 0) {
        window.addEventListener('scroll', () => {
            let current = '';
            
            serviceSections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                
                if (pageYOffset >= (sectionTop - 150)) {
                    current = section.getAttribute('id');
                }
            });
            
            serviceNavLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });
    }
}

// Initialize Services page functionality
document.addEventListener('DOMContentLoaded', function() {
    initServicesPage();
});

