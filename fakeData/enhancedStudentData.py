import random
import pandas as pd
import numpy as np
import os
import mysql.connector
from mysql.connector import Error

# Set seed for reproducibility
np.random.seed(42)

# MySQL Connection Parameters
mysql_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Standard XAMPP password is empty
    'database': 'dataUTAS'
}

# Define courses with their codes, names, and academic levels
courses = [
    # Diploma Level Courses (100-200 series)
    {"code": "EEE101", "name": "Basic Electrical Engineering", "level": "Diploma"},
    {"code": "EEE115", "name": "Introduction to Electronics", "level": "Diploma"},
    {"code": "EEE120", "name": "Electrical Circuits I", "level": "Diploma"},
    {"code": "EEE135", "name": "Digital Logic Design", "level": "Diploma"},
    {"code": "EEE145", "name": "Computer Programming", "level": "Diploma"},
    {"code": "EEE180", "name": "Electrical Measurement", "level": "Diploma"},
    {"code": "EEE201", "name": "Electrical Circuits II", "level": "Diploma"},
    {"code": "EEE210", "name": "Electronic Devices", "level": "Diploma"},
    {"code": "EEE220", "name": "Signals and Systems", "level": "Diploma"},
    {"code": "EEE235", "name": "Microcontrollers", "level": "Diploma"},
    
    # Bachelor Level Courses (300-400 series)
    {"code": "EEE301", "name": "Advanced Circuit Theory", "level": "Bachelor"},
    {"code": "EEE315", "name": "Power Electronics", "level": "Bachelor"},
    {"code": "EEE327", "name": "Digital Signal Processing", "level": "Bachelor"},
    {"code": "EEE333", "name": "Electromagnetic Fields", "level": "Bachelor"},
    {"code": "EEE342", "name": "Microprocessor Systems", "level": "Bachelor"},
    {"code": "EEE356", "name": "Control Systems", "level": "Bachelor"},
    {"code": "EEE371", "name": "Power Systems", "level": "Bachelor"},
    {"code": "EEE385", "name": "Communication Systems", "level": "Bachelor"},
    {"code": "EEE392", "name": "Digital Electronics", "level": "Bachelor"},
    {"code": "EEE403", "name": "VLSI Design", "level": "Bachelor"},
    {"code": "EEE418", "name": "Embedded Systems", "level": "Bachelor"},
    {"code": "EEE425", "name": "Renewable Energy Systems", "level": "Bachelor"}
]

# Total number of students across all levels
TOTAL_STUDENTS = 150

# Distribution of academic levels (percentage)
LEVEL_DISTRIBUTION = {
    "Diploma": 0.55,  # 55% in Diploma programs
    "Bachelor": 0.45  # 45% in Bachelor programs
}

# Generate student IDs based on academic year and level
def generate_student_ids(count, level):
    ids = set()
    # Different prefix for diploma (10d) and bachelor (12s)
    prefix = "10d" if level == "Diploma" else "12s"
    
    while len(ids) < count:
        middle_part = ''.join(random.choices('0123456789', k=3))
        # Different suffix for different enrollment years (23, 24, 25)
        # Newer students (25) are more common than older ones
        year_weights = {"23": 0.2, "24": 0.3, "25": 0.5}
        year_suffix = random.choices(list(year_weights.keys()), 
                                   weights=list(year_weights.values()), k=1)[0]
        ids.add(f"{prefix}{middle_part}{year_suffix}")
    return list(ids)

# Generate marks with variability based on course difficulty and student ability
def generate_marks(student_ability, course_difficulty):
    # Adjust base performance based on student ability and course difficulty
    # Higher ability = better performance, higher difficulty = lower performance
    performance_factor = 0.6 + (0.5 * student_ability) - (0.3 * course_difficulty)
    performance_factor = max(0.4, min(1.2, performance_factor))  # Clamp between 0.4 and 1.2
    
    # Generate individual marks
    test1 = min(10, max(0, round(random.normalvariate(7 * performance_factor, 1.5), 1)))
    midterm = min(20, max(0, round(random.normalvariate(14 * performance_factor, 3), 1)))
    test2 = min(10, max(0, round(random.normalvariate(7 * performance_factor, 1.5), 1)))
    assignment = min(10, max(3, round(random.normalvariate(8 * performance_factor, 1.2), 1)))
    
    # Calculate total and grade
    total = test1 + midterm + test2 + assignment
    
    grade = 'F'
    if total >= 45:
        grade = 'A+'
    elif total >= 40:
        grade = 'A'
    elif total >= 35:
        grade = 'B'
    elif total >= 30:
        grade = 'C'
    elif total >= 25:
        grade = 'D'
        
    return test1, midterm, test2, assignment, total, grade

def create_database_and_tables():
    """Create the database and necessary tables"""
    try:
        # First connect without specifying database to create it if needed
        conn = mysql.connector.connect(
            host=mysql_config['host'],
            user=mysql_config['user'],
            password=mysql_config['password']
        )
        
        if conn.is_connected():
            cursor = conn.cursor()
            
            # Create database if it doesn't exist
            cursor.execute(f"CREATE DATABASE IF NOT EXISTS {mysql_config['database']}")
            print(f"Database '{mysql_config['database']}' created or already exists")
            
            # Switch to the database
            cursor.execute(f"USE {mysql_config['database']}")
            
            # Create Students table with level
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS Students (
                    student_id VARCHAR(10) PRIMARY KEY,
                    academic_level VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            print("Students table created or already exists")
            
            # Create Courses table with level
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS Courses (
                    course_code VARCHAR(10) PRIMARY KEY,
                    course_name VARCHAR(100) NOT NULL,
                    academic_level VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            print("Courses table created or already exists")
            
            # Create CourseEnrollments table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS CourseEnrollments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id VARCHAR(10),
                    course_code VARCHAR(10),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES Students(student_id),
                    FOREIGN KEY (course_code) REFERENCES Courses(course_code),
                    UNIQUE (student_id, course_code)
                )
            """)
            print("CourseEnrollments table created or already exists")
            
            # Create StudentMarks table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS StudentMarks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id VARCHAR(10),
                    course_code VARCHAR(10),
                    test1 DECIMAL(5,2),
                    midterm DECIMAL(5,2),
                    test2 DECIMAL(5,2),
                    assignment DECIMAL(5,2),
                    total DECIMAL(5,2),
                    grade VARCHAR(5),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES Students(student_id),
                    FOREIGN KEY (course_code) REFERENCES Courses(course_code),
                    UNIQUE (student_id, course_code)
                )
            """)
            print("StudentMarks table created or already exists")
            
            conn.commit()
            return True
            
    except Error as e:
        print(f"Error: {e}")
        return False
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def insert_course_data():
    """Insert course data into the database"""
    try:
        conn = mysql.connector.connect(**mysql_config)
        
        if conn.is_connected():
            cursor = conn.cursor()
            
            # Insert course data
            for course in courses:
                cursor.execute(
                    "INSERT IGNORE INTO Courses (course_code, course_name, academic_level) VALUES (%s, %s, %s)",
                    (course['code'], course['name'], course['level'])
                )
            
            conn.commit()
            print(f"Inserted {len(courses)} courses into the database")
            
    except Error as e:
        print(f"Error: {e}")
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def generate_and_insert_student_data():
    """Generate realistic student data and insert into the database"""
    try:
        conn = mysql.connector.connect(**mysql_config)
        
        if conn.is_connected():
            cursor = conn.cursor()
            
            # Calculate number of students per level based on distribution
            diploma_count = int(TOTAL_STUDENTS * LEVEL_DISTRIBUTION["Diploma"])
            bachelor_count = TOTAL_STUDENTS - diploma_count
            
            print(f"Generating {diploma_count} Diploma students and {bachelor_count} Bachelor students")
            
            # Generate student IDs for each level
            diploma_students = generate_student_ids(diploma_count, "Diploma")
            bachelor_students = generate_student_ids(bachelor_count, "Bachelor")
            
            # Insert all students to the Students table with their level
            for student_id in diploma_students:
                cursor.execute(
                    "INSERT IGNORE INTO Students (student_id, academic_level) VALUES (%s, %s)",
                    (student_id, "Diploma")
                )
            
            for student_id in bachelor_students:
                cursor.execute(
                    "INSERT IGNORE INTO Students (student_id, academic_level) VALUES (%s, %s)",
                    (student_id, "Bachelor")
                )
            
            conn.commit()
            print(f"Inserted {len(diploma_students) + len(bachelor_students)} students into the database")
            
            # Filter courses by level
            diploma_courses = [course for course in courses if course['level'] == "Diploma"]
            bachelor_courses = [course for course in courses if course['level'] == "Bachelor"]
            
            # Define enrollment patterns with probabilities
            # Format: (primary_level_courses, other_level_courses, probability)
            enrollment_patterns = {
                "Diploma": [
                    (5, 0, 0.5),  # 50% take 5 diploma courses
                    (4, 0, 0.3),  # 30% take 4 diploma courses
                    (4, 1, 0.2),  # 20% take 4 diploma + 1 bachelor course
                ],
                "Bachelor": [
                    (5, 0, 0.6),  # 60% take 5 bachelor courses
                    (4, 0, 0.3),  # 30% take 4 bachelor courses
                    (4, 1, 0.1),  # 10% take 4 bachelor + 1 diploma course
                ]
            }
            
            # Process Diploma students
            for student_id in diploma_students:
                # Assign student ability (randomized but consistent for this student)
                # Using the student_id to create a consistent ability score
                student_ability = random.Random(student_id).random()
                
                # Choose enrollment pattern based on probability
                pattern_choice = random.random()
                cumulative_prob = 0
                selected_pattern = None
                
                for pattern in enrollment_patterns["Diploma"]:
                    cumulative_prob += pattern[2]
                    if pattern_choice <= cumulative_prob:
                        selected_pattern = pattern
                        break
                
                primary_course_count, other_course_count, _ = selected_pattern
                
                # Select random diploma courses
                selected_diploma_courses = random.sample(diploma_courses, primary_course_count)
                selected_bachelor_courses = []
                
                # If taking courses from the other level
                if other_course_count > 0:
                    selected_bachelor_courses = random.sample(bachelor_courses, other_course_count)
                
                # Combine all courses for this student
                student_courses = selected_diploma_courses + selected_bachelor_courses
                
                # Process each course enrollment for this student
                for course in student_courses:
                    # Insert course enrollment
                    cursor.execute(
                        "INSERT IGNORE INTO CourseEnrollments (student_id, course_code) VALUES (%s, %s)",
                        (student_id, course['code'])
                    )
                    
                    # Course difficulty is partly based on course code (higher = more difficult)
                    # and partly random per student-course combination
                    course_number = int(course['code'][3:])
                    base_difficulty = (course_number - 100) / 400  # Normalize to 0-1 range
                    course_difficulty = base_difficulty + random.uniform(-0.1, 0.1)
                    course_difficulty = max(0, min(1, course_difficulty))
                    
                    # Generate marks
                    test1, midterm, test2, assignment, total, grade = generate_marks(student_ability, course_difficulty)
                    
                    # Insert student marks
                    cursor.execute(
                        """
                        INSERT IGNORE INTO StudentMarks 
                        (student_id, course_code, test1, midterm, test2, assignment, total, grade) 
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                        """,
                        (
                            student_id, 
                            course['code'], 
                            test1, 
                            midterm, 
                            test2, 
                            assignment, 
                            total, 
                            grade
                        )
                    )
            
            # Process Bachelor students
            for student_id in bachelor_students:
                # Assign student ability
                student_ability = random.Random(student_id).random()
                
                # Choose enrollment pattern based on probability
                pattern_choice = random.random()
                cumulative_prob = 0
                selected_pattern = None
                
                for pattern in enrollment_patterns["Bachelor"]:
                    cumulative_prob += pattern[2]
                    if pattern_choice <= cumulative_prob:
                        selected_pattern = pattern
                        break
                
                primary_course_count, other_course_count, _ = selected_pattern
                
                # Select random bachelor courses
                selected_bachelor_courses = random.sample(bachelor_courses, primary_course_count)
                selected_diploma_courses = []
                
                # If taking courses from the other level
                if other_course_count > 0:
                    selected_diploma_courses = random.sample(diploma_courses, other_course_count)
                
                # Combine all courses for this student
                student_courses = selected_bachelor_courses + selected_diploma_courses
                
                # Process each course enrollment for this student
                for course in student_courses:
                    # Insert course enrollment
                    cursor.execute(
                        "INSERT IGNORE INTO CourseEnrollments (student_id, course_code) VALUES (%s, %s)",
                        (student_id, course['code'])
                    )
                    
                    # Course difficulty is partly based on course code and partly random
                    course_number = int(course['code'][3:])
                    base_difficulty = (course_number - 100) / 400  # Normalize to 0-1 range
                    course_difficulty = base_difficulty + random.uniform(-0.1, 0.1)
                    course_difficulty = max(0, min(1, course_difficulty))
                    
                    # Generate marks
                    test1, midterm, test2, assignment, total, grade = generate_marks(student_ability, course_difficulty)
                    
                    # Insert student marks
                    cursor.execute(
                        """
                        INSERT IGNORE INTO StudentMarks 
                        (student_id, course_code, test1, midterm, test2, assignment, total, grade) 
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                        """,
                        (
                            student_id, 
                            course['code'], 
                            test1, 
                            midterm, 
                            test2, 
                            assignment, 
                            total, 
                            grade
                        )
                    )
                    
            conn.commit()
            print("All student enrollments and marks have been inserted")
            
    except Error as e:
        print(f"Error: {e}")
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def run_summary_queries():
    """Run and display summary queries of the data"""
    try:
        conn = mysql.connector.connect(**mysql_config)
        
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            
            # Student distribution by level
            print("\n=== Student Distribution by Level ===")
            cursor.execute("""
                SELECT 
                    academic_level,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Students), 2) as percentage
                FROM 
                    Students
                GROUP BY 
                    academic_level
            """)
            levels = cursor.fetchall()
            for level in levels:
                print(f"{level['academic_level']}: {level['count']} students ({level['percentage']}%)")
            
            # Course enrollment statistics
            print("\n=== Course Enrollment Statistics ===")
            cursor.execute("""
                SELECT 
                    c.course_code,
                    c.course_name,
                    c.academic_level,
                    COUNT(ce.student_id) as enrollment_count
                FROM 
                    Courses c
                    LEFT JOIN CourseEnrollments ce ON c.course_code = ce.course_code
                GROUP BY 
                    c.course_code
                ORDER BY 
                    c.academic_level, c.course_code
            """)
            enrollments = cursor.fetchall()
            
            current_level = None
            for course in enrollments:
                if current_level != course['academic_level']:
                    current_level = course['academic_level']
                    print(f"\n{current_level} Level Courses:")
                    
                print(f"  {course['course_code']} - {course['course_name']}: {course['enrollment_count']} students")
            
            # Cross-level enrollment statistics
            print("\n=== Cross-Level Enrollment Statistics ===")
            cursor.execute("""
                SELECT 
                    s.academic_level as student_level,
                    c.academic_level as course_level,
                    COUNT(*) as enrollment_count
                FROM 
                    CourseEnrollments ce
                    JOIN Students s ON ce.student_id = s.student_id
                    JOIN Courses c ON ce.course_code = c.course_code
                GROUP BY 
                    s.academic_level, c.academic_level
            """)
            cross_levels = cursor.fetchall()
            
            for entry in cross_levels:
                print(f"{entry['student_level']} students in {entry['course_level']} courses: {entry['enrollment_count']}")
            
            # Grade distribution by level
            print("\n=== Grade Distribution by Level ===")
            cursor.execute("""
                SELECT 
                    s.academic_level,
                    sm.grade,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (
                        SELECT COUNT(*) FROM StudentMarks sm2 
                        JOIN Students s2 ON sm2.student_id = s2.student_id 
                        WHERE s2.academic_level = s.academic_level
                    ), 2) as percentage
                FROM 
                    StudentMarks sm
                    JOIN Students s ON sm.student_id = s.student_id
                GROUP BY 
                    s.academic_level, sm.grade
                ORDER BY 
                    s.academic_level, FIELD(sm.grade, 'A+', 'A', 'B', 'C', 'D', 'F')
            """)
            grade_dist = cursor.fetchall()
            
            current_level = None
            for grade in grade_dist:
                if current_level != grade['academic_level']:
                    current_level = grade['academic_level']
                    print(f"\n{current_level} Grade Distribution:")
                    
                print(f"  {grade['grade']}: {grade['count']} ({grade['percentage']}%)")
            
            # Course load distribution
            print("\n=== Course Load Distribution ===")
            cursor.execute("""
                SELECT 
                    s.academic_level,
                    COUNT(ce.course_code) as course_count,
                    COUNT(DISTINCT ce.student_id) as student_count
                FROM 
                    Students s
                    JOIN CourseEnrollments ce ON s.student_id = ce.student_id
                GROUP BY 
                    s.academic_level, ce.student_id
                ORDER BY 
                    s.academic_level, COUNT(ce.course_code)
            """)
            load_data = cursor.fetchall()
            
            # Process the data to get course load distribution
            load_distribution = {}
            for entry in load_data:
                level = entry['academic_level']
                course_count = entry['course_count']
                
                if level not in load_distribution:
                    load_distribution[level] = {}
                    
                if course_count not in load_distribution[level]:
                    load_distribution[level][course_count] = 0
                    
                load_distribution[level][course_count] += 1
            
            # Display the course load distribution
            for level, loads in load_distribution.items():
                print(f"\n{level} Course Load Distribution:")
                for course_count, student_count in sorted(loads.items()):
                    total_students = sum(loads.values())
                    percentage = round(student_count * 100 / total_students, 2)
                    print(f"  {course_count} courses: {student_count} students ({percentage}%)")
            
    except Error as e:
        print(f"Error: {e}")
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def main():
    print("Starting Enhanced Student Database Setup and Data Generation...")
    
    # Step 1: Create database and tables
    if not create_database_and_tables():
        print("Failed to create database structure. Exiting...")
        return
    
    # Step 2: Insert course data
    insert_course_data()
    
    # Step 3: Generate and insert student data
    generate_and_insert_student_data()
    
    # Step 4: Display summary information
    run_summary_queries()
    
    print("\nEnhanced student database setup and data generation completed successfully!")
    print(f"Database: {mysql_config['database']}")
    print("Tables created/modified: Students, Courses, CourseEnrollments, StudentMarks")
    print(f"Total students generated: {TOTAL_STUDENTS}")

if __name__ == "__main__":
    main() 