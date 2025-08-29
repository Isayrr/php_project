# Professional Resume Generator

A comprehensive, standalone resume generation system that creates professional, print-ready resumes in multiple formats without requiring database integration.

## Features

### 🎨 Multiple Professional Templates
- **Modern Corporate**: Clean, professional design for corporate roles
- **Minimalist Black & White**: Simple, ATS-friendly design focusing on content  
- **Elegant Color Accent**: Sophisticated design with subtle color accents
- **Creative Portfolio Style**: Bold, creative design for design/creative roles

### 📄 Output Formats
- **PDF**: High-quality, print-ready PDFs perfect for applications
- **Word Document (.docx)**: Editable format for easy customization

### 📋 Comprehensive Resume Sections

#### Required Sections
- **Contact Information**: Name, phone, email, LinkedIn/portfolio, address
- **Professional Summary**: Career goals and key achievements (tailored by career level)
- **Work Experience**: Job titles, companies, locations, dates, detailed descriptions
- **Education**: Degrees, schools, graduation years, honors, additional details
- **Skills**: Technical skills, soft skills, languages

#### Optional Sections
- **Certifications**: Professional certifications and licenses
- **Projects**: Notable projects and portfolio items
- **Awards & Achievements**: Recognition and accomplishments
- **Volunteer Experience**: Community involvement and volunteer work
- **Publications**: Articles, papers, and published works

### ✨ Key Benefits
- **ATS-Friendly**: All templates optimized for Applicant Tracking Systems
- **No Database Required**: Standalone system with complete privacy
- **Mobile Responsive**: Works perfectly on all devices
- **Professional Design**: Industry-standard layouts and typography
- **Quick Generation**: Create resumes in minutes

## File Structure

```
/
├── resume-generator.php    # Main form interface
├── generate-resume.php     # Backend processing and PDF/Word generation
├── resume-demo.php         # Landing page with features and demos
└── vendor/                 # Required libraries (MPDF, PHPWord)
```

## Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- Required PHP extensions: `mbstring`, `zip`, `xml`, `gd`
- Composer dependencies already included in `/vendor`

### Required Libraries
The system uses these pre-installed libraries:
- **MPDF**: For PDF generation
- **PHPWord**: For Word document generation

### Quick Start
1. Ensure your web server supports PHP 7.4+
2. Place files in your web directory
3. Access `resume-demo.php` for the landing page
4. Access `resume-generator.php` to start creating resumes

## Usage Guide

### 1. Template Selection
Choose from four professional templates designed for different industries and preferences.

### 2. Form Completion
Fill out the comprehensive form with:
- **Personal details** (required)
- **Career level** for tailored summary suggestions
- **Work experience** (add multiple positions)
- **Education** (add multiple degrees/certifications)
- **Skills** categorized by type
- **Optional sections** for additional content

### 3. Dynamic Sections
- **Add/Remove Experience**: Multiple work positions
- **Add/Remove Education**: Multiple educational backgrounds
- **Flexible Content**: All optional sections can be included/excluded

### 4. Generation
- Choose **PDF** for applications and printing
- Choose **Word** for easy editing and customization
- Instant download with professional formatting

## Template Specifications

### Layout Standards
- **Format**: A4 size (210 × 297 mm)
- **Length**: Optimized for 1-2 pages
- **Fonts**: Professional fonts (Helvetica, Calibri, Arial, Georgia)
- **Spacing**: Consistent margins and line spacing
- **Hierarchy**: Clear visual hierarchy with bold headers

### Color Schemes
- **Modern Corporate**: Blue (#3498db) and dark blue (#2c3e50)
- **Minimalist**: Black (#2c3e50) and grey (#95a5a6)
- **Elegant**: Red (#e74c3c) and dark blue (#34495e)  
- **Creative**: Purple (#9b59b6) and teal (#1abc9c)

### ATS Optimization
- Simple, clean layouts without complex graphics
- Standard fonts and formatting
- Proper heading structure
- No embedded images or graphics in text areas
- Standard section names recognized by ATS systems

## Technical Details

### PDF Generation
- Uses **MPDF library** for high-quality PDF output
- Professional formatting with proper margins
- Print-optimized styling
- Embedded fonts for consistency

### Word Generation
- Uses **PHPWord library** for .docx format
- Maintains formatting and styling
- Editable output for user customization
- Compatible with Microsoft Word and alternatives

### Security & Privacy
- **No database storage**: All data processed locally
- **No data retention**: Information not saved after generation
- **Local processing**: All generation happens on the server
- **Clean output**: Sanitized and validated input data

## Browser Compatibility
- Chrome 60+
- Firefox 60+  
- Safari 12+
- Edge 79+
- Mobile browsers supported

## Troubleshooting

### Common Issues
1. **PDF Generation Fails**: Check MPDF library and PHP memory limits
2. **Word Export Issues**: Verify PHPWord library installation
3. **Form Validation**: Ensure all required fields are completed
4. **Template Selection**: Must select a template before generation

### PHP Requirements
```bash
# Check required extensions
php -m | grep -E "(mbstring|zip|xml|gd)"
```

### Memory Requirements
- Minimum: 128MB PHP memory limit
- Recommended: 256MB for complex resumes

## License & Credits
- Built with MPDF and PHPWord libraries
- Font Awesome for icons
- Professional design templates
- Responsive CSS framework

## Support
For technical issues or questions:
1. Check that all PHP extensions are installed
2. Verify vendor libraries are present
3. Ensure proper file permissions
4. Check PHP error logs for detailed error information

---

**Start creating professional resumes today!** Access `resume-demo.php` to see features or `resume-generator.php` to begin building your resume. 