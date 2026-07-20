# Adaptive Practice for Moodle (mod_adaptivepractice)

![Adaptive Practice Dashboard](file:///C:/Users/Treeshainfotech/.gemini/antigravity/brain/d0b66395-28f0-424c-b02c-35bc58254161/practice_dashboard_1774950000692.png)

## 1. Overview
The **Adaptive Practice** module is a personalized learning activity for Moodle that dynamically adjusts the difficulty based on real-time student performance. It uses a **weighted competency algorithm** to serve content from three difficulty tiers (Easy, Medium, Hard).

### Key Features:
- **Hierarchical Category Selection**: Automatically includes sub-categories with visual tree indentation in the setup UI.
- **Smart Exclusion**: Remove problematic questions from practice by marking them as "Exclude", which applies an `ap_excluded` system tag.
- **Optimized SQL Statistics**: Reports use aggregate database functions for sub-second performance even with massive data sets.
- **Moodle 4.x/5.x Navigation**: Clean UI that integrates with Moodle's vertical tabs and removes redundant page-clutter.

---

## 2. Configuration & Setup
### Step 1: Select Categories
Browse your question bank categories. Selecting a parent category will show a recursive tree with question counts at every level.

### Step 2: Tiering
- **Manual**: Assign `easy`, `medium`, or `hard` tags to questions.
- **Auto-Assign**: Use the "Auto-Assign" button to randomly distribute questions across tiers based on your desired counts.
- **Exclude**: Simply choose "Exclude from Practice" to hide a question from students without deleting it.

![Question Configuration](file:///C:/Users/Treeshainfotech/.gemini/antigravity/brain/d0b66395-28f0-424c-b02c-35bc58254161/question_management_1774950018067.png)

### For Students:
1. **Start Practice**: Students see their current competency score and recommended level.
2. **Dynamic Flow**: If a student answers 2-3 questions in a row correctly, they are automatically bumped to the next tier in their *next* attempt.
3. **Review**: After completion, students receive detailed feedback on their answers and how their competency changed.

---

## 5. Architecture & Code Structure
- **`mod/adaptivepractice/lib.php`**: Contains Moodle core callbacks (Gradebook, Course Module deletion, completion hooks).
- **`mod/adaptivepractice/classes/helper.php`**: The central engine of the plugin. Handles the adaptive algorithm, SQL data fetching, and category hierarchy building.
- **`mod/adaptivepractice/questions.php`**: The administrative interface for curriculum mapping.
- **`mod/adaptivepractice/styles.css`**: Modern CSS design using CSS variables and scoped classes for a premium "Glassmorphism" look.

---

## 6. Database Schema
1. **`adaptivepractice`**: Main instance settings.
2. **`adaptivepractice_categories`**: Maps selected question categories to the activity.
3. **`adaptivepractice_attempts`**: Stores per-user session data and difficulty snapshots.
4. **`adaptivepractice_progress`**: Stores the persistent competency level for each user.

---

## 7. Performance & Security
- **Security Check**: Every endpoint validates `require_login`, `require_capability`, and `sesskey` to prevent unauthorized access.
- **Database Optimization**: Uses `LEFT JOIN` and SQL aggregate functions (`COUNT`, `AVG`) in the reports to avoid the "N+1" query problem.
- **CLI Utility**: Includes `cli/check_counts.php` for administrators to verify question bank health via the terminal.

---

## 8. Troubleshooting
- **Error: "Instance does not exist" during Uninstall**: Ensure you have upgraded to the latest version which includes the proper `adaptivepractice_grade_item_delete` callbacks.
- **No Questions Showing?**: Ensure your categories actually contain questions and that you have clicked "Save Category Selection" in Step 1.

---

## 9. Developer Notes
- **Coding Standards**: Adheres 100% to Moodle PHP Coding Styles (Frankenstyle naming).
- **Next Steps**: Integration with AI-driven difficulty analysis (Planned for V2.0).

![Performance Report](file:///C:/Users/Treeshainfotech/.gemini/antigravity/brain/d0b66395-28f0-424c-b02c-35bc58254161/performance_report_1774950048344.png)
