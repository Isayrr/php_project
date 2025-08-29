# Database Normalization for Job Portal System

This directory contains scripts and SQL files to normalize the database structure while maintaining full backward compatibility with the existing application code.

## The Problem

The current database structure has several normalization issues:

1. **Redundant data storage**: The `jobseekers` and `user_profiles` tables contain overlapping information.
2. **Denormalized skill data**: The `skill_matches` table stores skills as comma-separated text strings instead of proper relationships.
3. **Missing foreign key constraints**: Some tables lack proper foreign key constraints, which can lead to data integrity issues.
4. **Missing indexes**: Some frequently queried columns don't have indexes, potentially causing performance problems.

## The Solution

We've created a set of scripts that normalize the database while ensuring the application continues to work:

1. **Backup tables**: Create backup tables before making any changes
2. **Consolidated user profiles**: Move redundant jobseeker data into the user_profiles table
3. **Normalized skill matches**: Convert text-based skills into proper relationship records
4. **Add foreign keys**: Properly constrain tables to maintain data integrity
5. **Add performance indexes**: Improve query performance on commonly accessed fields

## Benefits of Normalization

- **Reduced data redundancy**: Eliminates duplicate data between jobseekers and user_profiles tables
- **Improved data integrity**: Foreign key constraints ensure relationships stay valid
- **Better performance**: Proper indexes on frequently queried columns improve speed
- **Easier maintenance**: Code will be easier to maintain with a properly normalized database
- **Backward compatibility**: All changes maintain compatibility with existing code

## How to Use These Scripts

### Option 1: Guided Web Interface (Recommended)

1. Make a complete backup of your database
2. Navigate to `http://yourdomain.com/JPAASM/normalize_database.php`
3. Follow the step-by-step instructions in the web interface

### Option 2: Manual SQL Execution

1. Make a complete backup of your database
2. Execute the SQL statements in `normalization_plan.sql`
3. Run the `normalize_skill_matches.php` script to convert text-based skills
4. Run the `consolidate_jobseekers.php` script to merge user profile data

### After Normalization

1. Test your application thoroughly to ensure everything works correctly
2. Monitor for any issues over the next few days
3. Once you're confident everything is working, you can optionally rename or remove the now-redundant tables

## Detailed Changes

### 1. User Profile Consolidation

- Data from `jobseekers` table is consolidated into `user_profiles`
- A compatibility view called `jobseekers_view` is created to maintain backward compatibility

### 2. Skill Matches Normalization

- Text-based skills in `skill_matches` are converted to proper relationships in a new `application_skill_match` table
- A trigger maintains backward compatibility by updating the old text columns when new skills are added

### 3. Foreign Key Constraints

- Proper foreign key constraints are added to maintain referential integrity
- All constraints use appropriate ON DELETE actions to prevent orphaned records

### 4. Performance Indexes

- Indexes are added on commonly queried columns to improve performance
- This includes status fields, date fields, and foreign key columns

## File Reference

- `normalize_database.php`: Web-based guided normalization process
- `normalization_plan.sql`: SQL script with all normalization steps
- `normalize_skill_matches.php`: Script to convert text-based skills to relationships
- `consolidate_jobseekers.php`: Script to merge redundant user profile data
- `README-database-normalization.md`: This documentation file 