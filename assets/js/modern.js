/**
 * Modern Interactive Features for Job Portal
 * Handles animations, smooth scrolling, counters, and dynamic effects
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize all modern features
    initNavbarEffects();
    initSmoothScrolling();
    initCounterAnimations();
    initScrollAnimations();
    initBackToTop();
    initFormEnhancements();
    initParallaxEffects();
    initPreloader();
    
    // Advanced Navbar Effects
    function initNavbarEffects() {
        const navbar = document.querySelector('.navbar');
        let lastScrollTop = 0;
        
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Add scrolled class for styling
            if (scrollTop > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            // Hide/show navbar on scroll
            if (scrollTop > lastScrollTop && scrollTop > 200) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop;
        });
    }
    
    // Smooth Scrolling for Anchor Links
    function initSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                
                if (target) {
                    const offsetTop = target.offsetTop - 80;
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }
    
    // Animated Counters
    function initCounterAnimations() {
        const counters = document.querySelectorAll('.counter');
        const observerOptions = {
            threshold: 0.7
        };
        
        const counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const target = parseInt(counter.getAttribute('data-target'));
                    const duration = 2000; // 2 seconds
                    const increment = target / (duration / 16); // 60fps
                    let current = 0;
                    
                    const updateCounter = () => {
                        current += increment;
                        if (current < target) {
                            counter.textContent = Math.floor(current);
                            requestAnimationFrame(updateCounter);
                        } else {
                            counter.textContent = target;
                        }
                    };
                    
                    updateCounter();
                    counterObserver.unobserve(counter);
                }
            });
        }, observerOptions);
        
        counters.forEach(counter => {
            counterObserver.observe(counter);
        });
    }
    
    // Scroll-triggered Animations
    function initScrollAnimations() {
        const animatedElements = document.querySelectorAll('[data-aos]');
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('aos-animate');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        animatedElements.forEach(el => {
            observer.observe(el);
        });
    }
    
    // Back to Top Button
    function initBackToTop() {
        const backToTopBtn = document.createElement('a');
        backToTopBtn.href = '#';
        backToTopBtn.className = 'back-to-top';
        backToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        document.body.appendChild(backToTopBtn);
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
        
        backToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Enhanced Form Interactions
    function initFormEnhancements() {
        // Floating labels
        const formControls = document.querySelectorAll('.form-control');
        
        formControls.forEach(control => {
            control.addEventListener('focus', function() {
                this.parentNode.classList.add('focused');
            });
            
            control.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentNode.classList.remove('focused');
                }
            });
            
            // Add ripple effect
            control.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Search suggestions (mock implementation)
        const searchInput = document.querySelector('input[name="keyword"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                if (value.length > 2) {
                    // Mock search suggestions
                    const suggestions = [
                        'Web Developer',
                        'Graphic Designer',
                        'Marketing Manager',
                        'Data Analyst',
                        'Software Engineer'
                    ].filter(item => item.toLowerCase().includes(value));
                    
                    showSuggestions(suggestions, this);
                }
            });
        }
    }
    
    // Show search suggestions
    function showSuggestions(suggestions, input) {
        let suggestionBox = document.querySelector('.suggestion-box');
        
        if (!suggestionBox) {
            suggestionBox = document.createElement('div');
            suggestionBox.className = 'suggestion-box glass position-absolute w-100 mt-1 rounded-3 p-2';
            suggestionBox.style.zIndex = '1050';
            input.parentNode.appendChild(suggestionBox);
        }
        
        suggestionBox.innerHTML = suggestions.map(suggestion => 
            `<div class="suggestion-item p-2 rounded cursor-pointer hover-bg-light">${suggestion}</div>`
        ).join('');
        
        // Add click handlers
        suggestionBox.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                input.value = this.textContent;
                suggestionBox.remove();
            });
        });
        
        // Remove on outside click
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !suggestionBox.contains(e.target)) {
                suggestionBox.remove();
            }
        });
    }
    
    // Parallax Effects
    function initParallaxEffects() {
        const parallaxElements = document.querySelectorAll('.floating-shapes .shape');
        
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset;
            
            parallaxElements.forEach((el, index) => {
                const speed = (index + 1) * 0.1;
                const yPos = -(scrollTop * speed);
                el.style.transform = `translateY(${yPos}px)`;
            });
        });
    }
    
    // Page Preloader
    function initPreloader() {
        const preloader = document.createElement('div');
        preloader.className = 'preloader position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        preloader.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
        preloader.style.zIndex = '9999';
        preloader.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Loading your dream job portal...</p>
            </div>
        `;
        
        document.body.appendChild(preloader);
        
        window.addEventListener('load', function() {
            preloader.style.opacity = '0';
            preloader.style.transition = 'opacity 0.5s ease-out';
            
            setTimeout(() => {
                preloader.remove();
            }, 500);
        });
    }
    
    // Card Hover Effects
    document.querySelectorAll('.job-card, .category-card, .testimonial-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Enhanced Button Interactions
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Add click ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('btn-ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Dynamic theme switching (optional feature)
    function initThemeToggle() {
        const themeToggle = document.createElement('button');
        themeToggle.className = 'btn btn-glass position-fixed';
        themeToggle.style.bottom = '100px';
        themeToggle.style.right = '25px';
        themeToggle.style.zIndex = '1000';
        themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        themeToggle.title = 'Toggle Dark Mode';
        
        document.body.appendChild(themeToggle);
        
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-theme');
            const icon = this.querySelector('i');
            
            if (document.body.classList.contains('dark-theme')) {
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }
    }
    
    // Typing animation for hero text
    function initTypingAnimation() {
        const heroTitle = document.querySelector('.hero-section h1');
        if (heroTitle) {
            const text = heroTitle.textContent;
            heroTitle.textContent = '';
            heroTitle.style.borderRight = '2px solid';
            
            let i = 0;
            const typeWriter = () => {
                if (i < text.length) {
                    heroTitle.textContent += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 100);
                } else {
                    heroTitle.style.borderRight = 'none';
                }
            };
            
            setTimeout(typeWriter, 1000);
        }
    }
    
    // Notification system
    function showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} position-fixed shadow-lg`;
        notification.style.top = '100px';
        notification.style.right = '25px';
        notification.style.zIndex = '1055';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove
        setTimeout(() => {
            notification.remove();
        }, duration);
    }
    
    // Initialize additional features
    // initThemeToggle(); // Uncomment to enable theme toggle
    // initTypingAnimation(); // Uncomment to enable typing animation
    
    console.log('🚀 Modern Job Portal features initialized successfully!');
});

// CSS for additional animations
const modernCSS = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    .btn-ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        animation: btn-ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes btn-ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    .suggestion-box {
        max-height: 200px;
        overflow-y: auto;
    }
    
    .suggestion-item:hover {
        background: rgba(102, 126, 234, 0.1);
    }
    
    .hover-bg-light:hover {
        background: rgba(0, 0, 0, 0.05);
    }
    
    .dark-theme {
        --bg-primary: #1a202c;
        --bg-secondary: #2d3748;
        --text-primary: #f7fafc;
        --text-secondary: #e2e8f0;
    }
    
    .dark-theme .card,
    .dark-theme .navbar,
    .dark-theme .form-control {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border-color: #4a5568;
    }
`;

// Inject modern CSS
const styleSheet = document.createElement('style');
styleSheet.textContent = modernCSS;
document.head.appendChild(styleSheet); 