<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Resume Generator - Demo</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .hero {
            text-align: center;
            color: white;
            padding: 60px 20px;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .cta-button {
            display: inline-block;
            background: #e74c3c;
            color: white;
            padding: 18px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .cta-button:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.3);
        }

        .templates-section {
            background: white;
            border-radius: 20px;
            padding: 50px;
            margin: 40px 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 50px;
        }

        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .template-preview {
            border: 3px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .template-preview:hover {
            border-color: #3498db;
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .template-visual {
            height: 200px;
            border-radius: 10px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .modern-visual {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        }

        .minimalist-visual {
            background: linear-gradient(135deg, #2c3e50 0%, #95a5a6 100%);
        }

        .elegant-visual {
            background: linear-gradient(135deg, #e74c3c 0%, #f39c12 100%);
        }

        .creative-visual {
            background: linear-gradient(135deg, #9b59b6 0%, #1abc9c 100%);
        }

        .template-preview h3 {
            font-size: 1.4rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .template-preview p {
            color: #666;
            line-height: 1.6;
        }

        .features-section {
            background: #2c3e50;
            color: white;
            border-radius: 20px;
            padding: 50px;
            margin: 40px 0;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 40px;
        }

        .feature {
            text-align: center;
        }

        .feature-icon {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
        }

        .feature h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .feature p {
            line-height: 1.6;
            opacity: 0.9;
        }

        .steps-section {
            background: white;
            border-radius: 20px;
            padding: 50px;
            margin: 40px 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .step {
            text-align: center;
            padding: 30px 20px;
            border-radius: 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }

        .step:hover {
            border-color: #3498db;
            background: #e3f2fd;
        }

        .step-number {
            display: inline-block;
            width: 60px;
            height: 60px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            line-height: 60px;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .step h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .step p {
            color: #666;
            line-height: 1.6;
        }

        .final-cta {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }

        .final-cta h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .final-cta p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .templates-section,
            .features-section,
            .steps-section {
                padding: 30px 20px;
            }

            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Hero Section -->
        <div class="hero">
            <h1><i class="fas fa-file-alt"></i> Professional Resume Generator</h1>
            <p>Create stunning, ATS-friendly resumes in minutes with our professional templates</p>
            <a href="resume-generator.php" class="cta-button">
                <i class="fas fa-rocket"></i> Create Your Resume Now
            </a>
        </div>

        <!-- Templates Section -->
        <div class="templates-section">
            <h2 class="section-title">Choose Your Perfect Template</h2>
            <div class="templates-grid">
                <div class="template-preview">
                    <div class="template-visual modern-visual"></div>
                    <h3>Modern Corporate</h3>
                    <p>Clean, professional design perfect for corporate roles and business environments. Features a sophisticated color scheme and modern typography.</p>
                </div>

                <div class="template-preview">
                    <div class="template-visual minimalist-visual"></div>
                    <h3>Minimalist Black & White</h3>
                    <p>Simple, elegant design that focuses on content. Perfect for any industry and guaranteed to pass ATS systems with flying colors.</p>
                </div>

                <div class="template-preview">
                    <div class="template-visual elegant-visual"></div>
                    <h3>Elegant Color Accent</h3>
                    <p>Sophisticated design with subtle color accents. Ideal for management roles and positions requiring a polished, professional appearance.</p>
                </div>

                <div class="template-preview">
                    <div class="template-visual creative-visual"></div>
                    <h3>Creative Portfolio Style</h3>
                    <p>Bold, creative design perfect for designers, marketers, and creative professionals who want to stand out from the crowd.</p>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="features-section">
            <h2 class="section-title">Why Choose Our Resume Generator?</h2>
            <div class="features-grid">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3>Multiple Formats</h3>
                    <p>Download your resume as PDF for applications or Word document for easy editing. Both formats are professionally formatted and print-ready.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>ATS-Friendly</h3>
                    <p>All our templates are optimized for Applicant Tracking Systems (ATS), ensuring your resume gets past automated screening processes.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-magic"></i>
                    </div>
                    <h3>Professional Design</h3>
                    <p>Our templates are designed by professionals and follow industry best practices for layout, typography, and visual hierarchy.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Quick & Easy</h3>
                    <p>Create a professional resume in just minutes. Our intuitive form guides you through each section with helpful tips and examples.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>Create your resume on any device. Our responsive design works perfectly on desktop, tablet, and mobile devices.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Privacy Focused</h3>
                    <p>Your data is processed locally and not stored in any database. Complete privacy and security for your personal information.</p>
                </div>
            </div>
        </div>

        <!-- Steps Section -->
        <div class="steps-section">
            <h2 class="section-title">How It Works</h2>
            <div class="steps-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Choose Template</h3>
                    <p>Select from our four professionally designed templates that suit your industry and personal style.</p>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Fill Information</h3>
                    <p>Complete the easy-to-use form with your personal details, experience, education, and skills.</p>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Customize Content</h3>
                    <p>Add optional sections like certifications, projects, awards, and volunteer experience to make your resume stand out.</p>
                </div>

                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Download & Apply</h3>
                    <p>Generate your professional resume in PDF or Word format and start applying to your dream jobs immediately.</p>
                </div>
            </div>
        </div>

        <!-- Final CTA -->
        <div class="final-cta">
            <h2>Ready to Land Your Dream Job?</h2>
            <p>Join thousands of professionals who have successfully created their resumes with our generator</p>
            <a href="resume-generator.php" class="cta-button">
                <i class="fas fa-arrow-right"></i> Start Building Your Resume
            </a>
        </div>
    </div>
</body>
</html> 