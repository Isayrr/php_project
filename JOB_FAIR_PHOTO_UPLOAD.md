# Job Fair Event Photo Upload Feature

## 📸 Overview

The Job Fair Event Photo Upload feature allows administrators to upload event banners/photos when creating events and enables employers to upload company photos/logos when registering for events.

## ✨ Features

### 🎯 **Admin Features**
- **Event Photo Upload**: Add promotional banners or event photos when creating new events
- **Photo Display**: View event photos in the admin events list and detailed event view
- **File Validation**: Automatic validation for file type and size

### 🏢 **Employer Features** 
- **Company Photo Upload**: Upload company logos or booth photos when registering for events
- **Photo Gallery**: View uploaded photos in the admin registration management
- **Easy Registration**: Seamless integration with existing registration process

## 📁 **Files Modified**

### Database Changes:
- `database/update_job_fair_photos.sql` - Adds photo columns to database tables

### Admin Side:
- `admin/job-fair-events.php` - Added event photo upload and display
- `admin/view-event.php` - Added event photo display and registration photo viewing

### Employer Side:
- `employer/job-fair-events.php` - Added event photo display and registration photo upload

## 🗄️ **Database Schema**

### New Columns Added:
```sql
-- Event photos
ALTER TABLE job_fair_events 
ADD COLUMN event_photo VARCHAR(255) NULL AFTER event_description;

-- Registration photos  
ALTER TABLE event_registrations 
ADD COLUMN registration_photo VARCHAR(255) NULL AFTER notes;
```

## 📤 **Upload Specifications**

### Event Photos:
- **Location**: `uploads/job_fair_events/`
- **File Types**: JPG, PNG, GIF
- **Max Size**: 5MB
- **Naming**: `event_[timestamp]_[uniqid].[extension]`
- **Dimensions**: Auto-resized for display (200px height for cards, 300px max for detail view)

### Registration Photos:
- **Location**: `uploads/event_registrations/`  
- **File Types**: JPG, PNG, GIF
- **Max Size**: 3MB
- **Naming**: `registration_[event_id]_[user_id]_[timestamp].[extension]`
- **Dimensions**: 60x60px thumbnails in admin view

## 🖼️ **Photo Display**

### Event Photos:
- **Employer View**: Card header images (200px height)
- **Admin List**: 60x60px thumbnails in events table
- **Admin Detail**: Full-size display (max 300px height)

### Registration Photos:
- **Admin View**: 60x60px thumbnails in registrations table
- **Click to Enlarge**: Modal popup for full-size viewing
- **Fallback**: "No Photo" placeholder for empty uploads

## 🔧 **Implementation Details**

### Photo Upload Process:
1. **File Validation**: Check file type, size, and upload errors
2. **Directory Creation**: Auto-create upload directories if needed
3. **Unique Naming**: Generate unique filenames to prevent conflicts
4. **Database Storage**: Store relative path in database
5. **Error Handling**: Comprehensive error messages for failed uploads

### Security Features:
- **File Type Validation**: Only allow image files (JPG, PNG, GIF)
- **Size Limits**: Prevent large file uploads
- **Upload Directory**: Restricted to designated folders
- **Path Sanitization**: Prevent directory traversal attacks

## 📋 **Setup Instructions**

### 1. Database Update
Run the database update script:
```sql
SOURCE database/update_job_fair_photos.sql;
```

### 2. Directory Permissions
Ensure upload directories are writable:
```bash
chmod 755 uploads/job_fair_events/
chmod 755 uploads/event_registrations/
```

### 3. Test Upload
1. Create a new job fair event with a photo
2. Register as an employer with a company photo
3. Verify photos display correctly in admin and employer views

## 🎨 **UI/UX Enhancements**

### Event Cards:
- **Visual Appeal**: Event photos make events more attractive
- **Professional Look**: Enhanced branding with promotional images
- **Consistent Layout**: Maintains card structure with optional images

### Admin Management:
- **Quick Identification**: Thumbnail previews in tables
- **Photo Modal**: Click-to-enlarge functionality
- **Visual Organization**: Easy to distinguish events and companies

### Registration Process:
- **Company Branding**: Employers can showcase their brand
- **Booth Preparation**: Visual reference for event organizers
- **Professional Presentation**: Enhanced company representation

## 📊 **File Management**

### Automatic Features:
- **Directory Creation**: Upload folders created automatically
- **Unique Naming**: Prevents filename conflicts
- **Old File Cleanup**: Placeholder for future cleanup implementation

### Manual Management:
- **File Access**: Direct access via file system
- **Backup Considerations**: Include upload folders in backups
- **Storage Monitoring**: Monitor disk space usage

## 🐛 **Troubleshooting**

### Common Issues:

**Upload Fails:**
- Check file permissions on upload directories
- Verify file size doesn't exceed limits
- Ensure correct file types (JPG, PNG, GIF only)

**Photos Not Displaying:**
- Check file paths in database
- Verify upload directory structure
- Confirm web server can serve static files

**Performance Issues:**
- Optimize image sizes before upload
- Consider image compression for large files
- Monitor server storage space

### Debug Tips:
```php
// Check upload directory permissions
echo "Upload dir writable: " . (is_writable('../uploads/job_fair_events/') ? 'Yes' : 'No');

// Verify file upload settings
echo "Max upload size: " . ini_get('upload_max_filesize');
echo "Max post size: " . ini_get('post_max_size');
```

## 🚀 **Future Enhancements**

Potential improvements:
- **Image Compression**: Automatic compression for uploaded images
- **Multiple Photos**: Support for photo galleries per event
- **Photo Cropping**: Built-in image editing tools
- **Watermarks**: Automatic watermarking for event photos
- **CDN Integration**: External storage for better performance

## 📸 **Usage Examples**

### Creating Event with Photo:
1. Go to Admin → Job Fair Events
2. Click "Add New Event"
3. Fill event details
4. Upload event banner/photo
5. Submit form

### Registering with Company Photo:
1. Go to Employer → Job Fair Events  
2. Click "Register for Event"
3. Upload company logo/photo
4. Add notes and submit

### Viewing Photos:
- **Events List**: Photos display as card headers
- **Admin Table**: Thumbnails in photo column
- **Registration Table**: Click thumbnails to enlarge

---

**Version**: 1.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+ with file upload support 