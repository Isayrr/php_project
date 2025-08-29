# Normalized Database ERD

```mermaid
erDiagram
    users ||--o{ user_profiles : "has profile"
    users ||--o{ companies : "can own"
    users {
        int user_id PK
        varchar username UK
        varchar password
        varchar email UK
        enum role "admin/employer/jobseeker"
        timestamp created_at
        enum status "active/inactive"
        enum approval_status "pending/approved/rejected"
    }
    
    user_profiles {
        int profile_id PK
        int user_id FK
        varchar first_name
        varchar last_name
        varchar phone
        text address
        varchar experience
        varchar profile_picture
        text bio
        varchar resume
        varchar cover_letter
    }
    
    companies {
        int company_id PK
        int employer_id FK "references users.user_id"
        varchar company_name
        varchar industry
        varchar company_size
        text company_description
        varchar company_website
        varchar company_logo
    }
    
    job_categories ||--o{ jobs : "categorizes"
    job_categories {
        int category_id PK
        varchar category_name
        text description
        timestamp created_at
    }
    
    companies ||--o{ jobs : "posts"
    jobs {
        int job_id PK
        int company_id FK
        int category_id FK
        varchar title
        text description
        text requirements
        varchar salary_range
        enum job_type "full-time/part-time/contract/internship"
        varchar industry
        varchar location
        timestamp posted_date
        date deadline_date
        enum status "active/closed"
        enum approval_status "pending/approved/rejected"
        int vacancies
        boolean featured
    }
    
    jobs ||--o{ job_skills : "requires"
    skills ||--o{ job_skills : "is required by"
    job_skills {
        int job_id PK,FK
        int skill_id PK,FK
        enum required_level "beginner/intermediate/expert"
    }
    
    skills {
        int skill_id PK
        varchar skill_name UK
        text description
        int priority
    }
    
    users ||--o{ jobseeker_skills : "has skill"
    skills ||--o{ jobseeker_skills : "belongs to"
    jobseeker_skills {
        int jobseeker_id PK,FK "references users.user_id"
        int skill_id PK,FK
        enum proficiency_level "beginner/intermediate/expert"
    }
    
    jobs ||--o{ applications : "receives"
    users ||--o{ applications : "applies to"
    applications {
        int application_id PK
        int job_id FK
        int jobseeker_id FK "references users.user_id"
        timestamp application_date
        enum status "pending/reviewed/interviewed/shortlisted/rejected/hired"
        text cover_letter
        varchar resume_path
    }
    
    applications ||--o{ skill_matches : "has match data"
    skill_matches {
        int application_id PK,FK
        decimal match_score
        text matching_skills "legacy"
        text missing_skills "legacy"
    }
    
    applications ||--o{ application_skill_match : "includes"
    skills ||--o{ application_skill_match : "is included in"
    application_skill_match {
        int application_id PK,FK
        int skill_id PK,FK
        boolean is_matching "true=matching, false=missing"
    }
    
    users ||--o{ resumes : "creates"
    resumes {
        int resume_id PK
        int user_id FK
        varchar title
        text description
        int template_id
        varchar photo
        datetime created_at
        datetime updated_at
    }
    
    resumes ||--o{ resume_sections : "contains"
    resume_sections {
        int section_id PK
        int resume_id FK
        enum section_type "personal/summary/education/experience/skills/etc"
        varchar section_title
        int section_order
        text content
        longtext metadata
        datetime created_at
        datetime updated_at
    }
    
    resume_sections ||--o{ resume_education : "may include"
    resume_education {
        int education_id PK
        int section_id FK
        varchar institution
        varchar degree
        varchar field_of_study
        date start_date
        date end_date
        boolean is_current
        text description
        int order_index
    }
    
    resume_sections ||--o{ resume_experience : "may include"
    resume_experience {
        int experience_id PK
        int section_id FK
        varchar company
        varchar position
        varchar location
        date start_date
        date end_date
        boolean is_current
        text description
        text responsibilities
        int order_index
    }
    
    resume_sections ||--o{ resume_skills : "may include"
    resume_skills {
        int skill_id PK
        int section_id FK
        varchar skill_name
        enum proficiency_level "beginner/intermediate/advanced/expert"
        int years_experience
        int order_index
    }
    
    users ||--o{ notifications : "receives"
    notifications {
        int notification_id PK
        int user_id FK
        varchar title
        text message
        int related_id
        varchar related_type
        boolean is_read
        timestamp created_at
    }
```

## Key Features of the Normalized Structure

1. **Clear Entity Relationships**:
   - All relationships are properly defined with appropriate cardinality
   - Foreign keys are explicitly marked

2. **Bridge Tables for Many-to-Many Relationships**:
   - `job_skills`: Connects jobs to required skills
   - `jobseeker_skills`: Connects users to their skills
   - `application_skill_match`: Normalized way to store skill matches

3. **User Data Consolidation**:
   - User profile data consolidated in `user_profiles` table
   - Proper relation to the `users` table

4. **Elimination of Text-Based Lists**:
   - Skills stored in proper relationship tables
   - No more comma-separated lists

5. **Support for Complex Resume Structure**:
   - Hierarchical resume design with sections and components
   - Allows for rich resume content while maintaining normalization 