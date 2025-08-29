<?php
/**
 * Common data used across the application
 * This file contains standard lists and functions for reusable data
 */

/**
 * Get a standardized list of industries
 * @return array Array of industry names
 */
function getIndustriesList() {
    return [
        'Accounting',
        'Agriculture, Forestry & Fishing',
        'Automotive',
        'Banking & Finance',
        'Business Services',
        'Construction',
        'Consulting',
        'Education',
        'Engineering',
        'Entertainment & Media',
        'Food & Beverage',
        'Government',
        'Healthcare',
        'Hospitality & Tourism',
        'Information Technology',
        'Insurance',
        'Legal Services',
        'Manufacturing',
        'Marketing & Advertising',
        'Mining & Resources',
        'Non-Profit & Social Services',
        'Oil & Gas',
        'Pharmaceuticals',
        'Real Estate',
        'Retail',
        'Telecommunications',
        'Transportation & Logistics',
        'Utilities & Energy',
        'Others'
    ];
}

/**
 * Get industries from database or fallback to standard list
 * 
 * @param PDO $conn Database connection
 * @param bool $includeStandard Whether to merge database results with standard list
 * @return array Array of industry names
 */
function getIndustries($conn, $includeStandard = true) {
    try {
        // Get available industries from database
        $stmt = $conn->query("SELECT DISTINCT industry FROM companies WHERE industry IS NOT NULL ORDER BY industry");
        $dbIndustries = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($includeStandard) {
            // Merge with standard industries and remove duplicates
            $standardIndustries = getIndustriesList();
            $industries = array_unique(array_merge($dbIndustries, $standardIndustries));
            sort($industries);
            return $industries;
        }
        
        return $dbIndustries;
    } catch (PDOException $e) {
        // If database query fails, return standard list
        return getIndustriesList();
    }
}

/**
 * Render an industry dropdown select
 * 
 * @param string $name The name attribute of the select element
 * @param string $id The id attribute of the select element
 * @param string $selectedValue Currently selected value
 * @param array $industries List of industries to include
 * @param string $class Additional CSS classes
 * @param bool $required Whether the field is required
 * @return string HTML for the dropdown
 */
function renderIndustryDropdown($name, $id, $selectedValue = '', $industries = null, $class = 'form-select', $required = false) {
    if ($industries === null) {
        $industries = getIndustriesList();
    }
    
    $html = '<select class="' . $class . '" id="' . $id . '" name="' . $name . '"' . ($required ? ' required' : '') . '>';
    $html .= '<option value="">Select Industry</option>';
    
    foreach ($industries as $industry) {
        $selected = ($selectedValue == $industry) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($industry) . '" ' . $selected . '>';
        $html .= htmlspecialchars($industry);
        $html .= '</option>';
    }
    
    $html .= '</select>';
    return $html;
} 