<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Resume Generator</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .form-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 40px;
        }

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .template-selection {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .template-card {
            border: 3px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .template-card:hover {
            border-color: #3498db;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .template-card.selected {
            border-color: #3498db;
            background: #e3f2fd;
        }

        .template-card input[type="radio"] {
            display: none;
        }

        .dynamic-section {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }

        .add-button,
        .remove-button {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .add-button {
            background: #28a745;
            color: white;
        }

        .add-button:hover {
            background: #218838;
        }

        .remove-button {
            background: #dc3545;
            color: white;
            float: right;
        }

        .remove-button:hover {
            background: #c82333;
        }

        .generate-section {
            grid-column: 1 / -1;
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            color: white;
        }

        .generate-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-pdf {
            background: #e74c3c;
            color: white;
        }

        .btn-word {
            background: #2980b9;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .skills-container {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .form-container {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .generate-buttons {
                flex-direction: column;
                align-items: center;
            }

            .template-selection {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-alt"></i> Professional Resume Generator</h1>
            <p>Create a stunning, print-ready resume in minutes with our professional templates</p>
        </div>

        <form id="resumeForm" action="generate-resume.php" method="POST">
            <div class="form-container">
                <!-- Template Selection -->
                <div class="form-section template-selection">
                    <h3><i class="fas fa-palette"></i> Choose Your Template</h3>
                    
                    <div class="template-card" onclick="selectTemplate('modern')">
                        <input type="radio" name="template" value="modern" id="modern" required>
                        <label for="modern">
                            <h4>Modern Corporate</h4>
                            <p>Clean, professional design perfect for corporate roles</p>
                            <div style="background: linear-gradient(45deg, #3498db, #2c3e50); height: 60px; margin: 10px 0; border-radius: 5px;"></div>
                        </label>
                    </div>

                    <div class="template-card" onclick="selectTemplate('minimalist')">
                        <input type="radio" name="template" value="minimalist" id="minimalist">
                        <label for="minimalist">
                            <h4>Minimalist Black & White</h4>
                            <p>Simple, clean design that focuses on content</p>
                            <div style="background: linear-gradient(45deg, #2c3e50, #95a5a6); height: 60px; margin: 10px 0; border-radius: 5px;"></div>
                        </label>
                    </div>

                    <div class="template-card" onclick="selectTemplate('elegant')">
                        <input type="radio" name="template" value="elegant" id="elegant">
                        <label for="elegant">
                            <h4>Elegant Color Accent</h4>
                            <p>Sophisticated design with subtle color accents</p>
                            <div style="background: linear-gradient(45deg, #e74c3c, #f39c12); height: 60px; margin: 10px 0; border-radius: 5px;"></div>
                        </label>
                    </div>

                    <div class="template-card" onclick="selectTemplate('creative')">
                        <input type="radio" name="template" value="creative" id="creative">
                        <label for="creative">
                            <h4>Creative Portfolio Style</h4>
                            <p>Bold, creative design for design and creative roles</p>
                            <div style="background: linear-gradient(45deg, #9b59b6, #1abc9c); height: 60px; margin: 10px 0; border-radius: 5px;"></div>
                        </label>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Contact Information</h3>
                    
                    <div class="form-group">
                        <label for="fullName">Full Name *</label>
                        <input type="text" id="fullName" name="fullName" required placeholder="John Doe">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="+1 (555) 123-4567">
                    </div>

                    <div class="form-group">
                        <label for="email">Professional Email *</label>
                        <input type="email" id="email" name="email" required placeholder="john.doe@email.com">
                    </div>

                    <div class="form-group">
                        <label for="linkedin">LinkedIn/Portfolio URL</label>
                        <input type="url" id="linkedin" name="linkedin" placeholder="https://linkedin.com/in/johndoe">
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" placeholder="City, State, Country">
                    </div>
                </div>

                <!-- Professional Summary -->
                <div class="form-section">
                    <h3><i class="fas fa-bullseye"></i> Professional Summary</h3>
                    
                    <div class="form-group">
                        <label for="careerLevel">Career Level *</label>
                        <select id="careerLevel" name="careerLevel" required>
                            <option value="">Select your level</option>
                            <option value="entry">Entry Level (0-2 years)</option>
                            <option value="junior">Junior Professional (2-5 years)</option>
                            <option value="mid">Mid-Level Professional (5-8 years)</option>
                            <option value="senior">Senior Professional (8-12 years)</option>
                            <option value="lead">Lead/Manager (12+ years)</option>
                            <option value="executive">Executive Level</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="summary">Professional Summary *</label>
                        <textarea id="summary" name="summary" required 
                                placeholder="Write a compelling 2-3 sentence summary highlighting your key achievements, skills, and career goals. Focus on what makes you unique and valuable to potential employers."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="objective">Career Objective (Optional)</label>
                        <textarea id="objective" name="objective" 
                                placeholder="Describe your career goals and what you're looking to achieve in your next role."></textarea>
                    </div>
                </div>

                <!-- Work Experience -->
                <div class="form-section">
                    <h3><i class="fas fa-briefcase"></i> Work Experience</h3>
                    
                    <div id="experienceContainer">
                        <div class="dynamic-section">
                            <button type="button" class="remove-button" onclick="removeExperience(this)">Remove</button>
                            
                            <div class="form-group">
                                <label>Job Title *</label>
                                <input type="text" name="jobTitle[]" required placeholder="Software Developer">
                            </div>

                            <div class="form-group">
                                <label>Company Name *</label>
                                <input type="text" name="company[]" required placeholder="ABC Tech Solutions">
                            </div>

                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location[]" placeholder="New York, NY">
                            </div>

                            <div class="form-group">
                                <label>Start Date *</label>
                                <input type="month" name="startDate[]" required>
                            </div>

                            <div class="form-group">
                                <label>End Date (Leave blank if current)</label>
                                <input type="month" name="endDate[]">
                            </div>

                            <div class="form-group">
                                <label>Job Description & Achievements *</label>
                                <textarea name="jobDescription[]" required 
                                        placeholder="• Developed and maintained web applications using React and Node.js&#10;• Collaborated with cross-functional teams to deliver projects on time&#10;• Improved application performance by 30% through code optimization"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="add-button" onclick="addExperience()">
                        <i class="fas fa-plus"></i> Add Experience
                    </button>
                </div>

                <!-- Education -->
                <div class="form-section">
                    <h3><i class="fas fa-graduation-cap"></i> Education</h3>
                    
                    <div id="educationContainer">
                        <div class="dynamic-section">
                            <button type="button" class="remove-button" onclick="removeEducation(this)">Remove</button>
                            
                            <div class="form-group">
                                <label>Degree/Certification *</label>
                                <input type="text" name="degree[]" required placeholder="Bachelor of Science in Computer Science">
                            </div>

                            <div class="form-group">
                                <label>School/Institution *</label>
                                <input type="text" name="school[]" required placeholder="University of Technology">
                            </div>

                            <div class="form-group">
                                <label>Graduation Year *</label>
                                <input type="number" name="gradYear[]" required min="1950" max="2030" placeholder="2023">
                            </div>

                            <div class="form-group">
                                <label>GPA/Honors</label>
                                <input type="text" name="honors[]" placeholder="3.8 GPA, Magna Cum Laude">
                            </div>

                            <div class="form-group">
                                <label>Additional Details</label>
                                <textarea name="eduDetails[]" placeholder="Relevant coursework, projects, or achievements"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="add-button" onclick="addEducation()">
                        <i class="fas fa-plus"></i> Add Education
                    </button>
                </div>

                <!-- Skills -->
                <div class="form-section">
                    <h3><i class="fas fa-cogs"></i> Skills</h3>
                    
                    <div class="form-group">
                        <label>Technical Skills *</label>
                        <textarea id="technicalSkills" name="technicalSkills" required 
                                placeholder="Python, JavaScript, React, SQL, AWS, Docker, Git, etc. (separate with commas)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Soft Skills</label>
                        <textarea id="softSkills" name="softSkills" 
                                placeholder="Leadership, Communication, Problem Solving, Team Collaboration, etc. (separate with commas)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Languages</label>
                        <textarea id="languages" name="languages" 
                                placeholder="English (Native), Spanish (Fluent), French (Conversational), etc."></textarea>
                    </div>
                </div>

                <!-- Optional Sections -->
                <div class="form-section">
                    <h3><i class="fas fa-star"></i> Additional Sections (Optional)</h3>
                    
                    <div class="form-group">
                        <label>Certifications</label>
                        <textarea name="certifications" 
                                placeholder="AWS Certified Solutions Architect (2023)&#10;Google Analytics Certified (2022)&#10;Project Management Professional (PMP) (2021)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Projects</label>
                        <textarea name="projects" 
                                placeholder="E-commerce Platform - Built a full-stack e-commerce solution using React and Node.js&#10;Mobile App Development - Created a productivity app with 10k+ downloads"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Awards & Achievements</label>
                        <textarea name="awards" 
                                placeholder="Employee of the Month (March 2023)&#10;Dean's List (2021-2022)&#10;Hackathon Winner - Best Innovation Award"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Volunteer Experience</label>
                        <textarea name="volunteer" 
                                placeholder="Volunteer Web Developer - Local Non-Profit (2022-Present)&#10;Mentorship Program - Guided 5 junior developers"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Publications</label>
                        <textarea name="publications" 
                                placeholder="Technical articles, research papers, blog posts, etc."></textarea>
                    </div>
                </div>

                <!-- Generate Section -->
                <div class="generate-section">
                    <h3><i class="fas fa-download"></i> Generate Your Resume</h3>
                    <p>Choose your preferred format and download your professional resume</p>
                    
                    <div class="generate-buttons">
                        <button type="submit" name="format" value="pdf" class="btn btn-pdf">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </button>
                        <button type="submit" name="format" value="word" class="btn btn-word">
                            <i class="fas fa-file-word"></i> Download Word
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function selectTemplate(template) {
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            document.getElementById(template).checked = true;
            document.getElementById(template).closest('.template-card').classList.add('selected');
        }

        function addExperience() {
            const container = document.getElementById('experienceContainer');
            const newExperience = container.children[0].cloneNode(true);
            
            // Clear all input values
            newExperience.querySelectorAll('input, textarea').forEach(input => {
                input.value = '';
                input.removeAttribute('required');
            });
            
            container.appendChild(newExperience);
        }

        function removeExperience(button) {
            const container = document.getElementById('experienceContainer');
            if (container.children.length > 1) {
                button.closest('.dynamic-section').remove();
            }
        }

        function addEducation() {
            const container = document.getElementById('educationContainer');
            const newEducation = container.children[0].cloneNode(true);
            
            // Clear all input values
            newEducation.querySelectorAll('input, textarea').forEach(input => {
                input.value = '';
                input.removeAttribute('required');
            });
            
            container.appendChild(newEducation);
        }

        function removeEducation(button) {
            const container = document.getElementById('educationContainer');
            if (container.children.length > 1) {
                button.closest('.dynamic-section').remove();
            }
        }

        // Form validation
        document.getElementById('resumeForm').addEventListener('submit', function(e) {
            const template = document.querySelector('input[name="template"]:checked');
            if (!template) {
                e.preventDefault();
                alert('Please select a template before generating your resume.');
                return;
            }
        });
    </script>
</body>
</html> 