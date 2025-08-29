# Database Normalization Process

This package contains scripts to normalize your Job Portal database while maintaining backward compatibility with existing application code.

## Important Notes Before Starting

1. **ALWAYS BACKUP YOUR DATABASE** before running any normalization scripts
2. Test in a staging environment first if possible
3. Schedule this during low-traffic periods
4. Review the [database_normalization_report.md](database_normalization_report.md) to understand all changes

## Normalization Files

This package includes the following files:

- `complete_normalization.php` - The main script to run the full normalization process
- `database_normalization_report.md` - Detailed report explaining all normalization changes
- `normalized_database_erd.md` - Entity Relationship Diagram of the normalized structure
- `NORMALIZATION-README.md` - This instruction file

## Running the Normalization Process

### Step 1: Backup Your Database

```sql
-- From MySQL command line
mysqldump -u username -p job_portal > job_portal_backup.sql

-- Or from the command line
php -r "system('mysqldump -u root -p job_portal > job_portal_backup_' . date('Y-m-d_H-i-s') . '.sql');"
```

### Step 2: Run the Complete Normalization Script

Navigate to the script in your web browser:
```
http://localhost:8080/JPAASM/complete_normalization.php
```

The script will:
1. Create backup tables for safety
2. Consolidate redundant user data
3. Normalize skill matches tables
4. Add missing foreign key constraints
5. Add performance indexes
6. Maintain backward compatibility through views and triggers

### Step 3: Verify Everything Works

After running the script:

1. Test all major application features to ensure they work correctly
2. Pay special attention to:
   - User profile functionality
   - Job application processes
   - Skill matching features
   - Reporting and analytics

### Step 4: Final Cleanup (Optional)

After verifying everything works for at least a few days/weeks, you can remove the redundant jobseekers table:

```sql
DROP TABLE jobseekers;
```

> **Note:** Only perform this step after thoroughly verifying that all application features work correctly.

## Troubleshooting

If you encounter any issues:

1. Check the error messages displayed by the normalization script
2. Restore from backup if necessary
3. Common issues:
   - Foreign key constraint failures (existing orphaned records)
   - Trigger creation issues (MySQL permission problems)
   - Duplicate entries during consolidation

## Database Structure Reference

For a complete view of the normalized structure, see the [normalized_database_erd.md](normalized_database_erd.md) file.

## Normalization Benefits

This normalization process:

1. Eliminates redundant data storage
2. Improves data integrity through proper constraints
3. Enhances query performance with optimized indexes
4. Makes the database easier to maintain and extend
5. Preserves full backward compatibility with existing code

For complete details on all changes and benefits, see the [database_normalization_report.md](database_normalization_report.md) file. 