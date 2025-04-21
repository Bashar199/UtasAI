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

# Generate student IDs based on academic year and level
def generate_student_ids(count, level):
    ids = set()
    # Different prefix for diploma (10d) and bachelor (12s)
    prefix = "10d" if level == "Diploma" else "12s"
    
    while len(ids) < count:
        middle_part = ''.join(random.choices('0123456789', k=3))
        # Different suffix for different enrollment years (23, 24, 25)
        year_suffix = random.choice(["23", "24", "25"])
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
    
    return test1, midterm, test2, assignment

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

def generate_and_insert_realistic_student_data():
    """Generate realistic student data and insert into the database"""
    try:
        conn = mysql.connector.connect(**mysql_config)
        
        if conn.is_connected():
            cursor = conn.cursor()
            
            # Step 1: Divide students between Diploma and Bachelor levels (60% Diploma, 40% Bachelor)
            diploma_count = int(TOTAL_STUDENTS * 0.6)
            bachelor_count = TOTAL_STUDENTS - diploma_count
            
            # Generate student IDs for each level
            diploma_students = generate_student_ids(diploma_count, "Diploma")
            bachelor_students = generate_student_ids(bachelor_count, "Bachelor")
            
            # Step 2: Insert all students to the Students table with their level
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
            
            # Define course enrollment patterns
            # Each pattern is (primary_level_courses, other_level_courses)
            enrollment_patterns = {
                "Diploma": [
                    (5, 0),  # 5 diploma courses, 0 bachelor courses
                    (4, 0),  # 4 diploma courses, 0 bachelor courses
                    (4, 1),  # 4 diploma courses, 1 bachelor course
                ],
                "Bachelor": [
                    (5, 0),  # 5 bachelor courses, 0 diploma courses
                    (4, 0),  # 4 bachelor courses, 0 diploma courses
                    (4, 1),  # 4 bachelor courses, 1 diploma course (e.g., retaking)
                ]
            }
            
            # Step 3: Assign courses to students based on patterns
            for student_id in diploma_students:
                # Assign each student a random ability level (0.0 to 1.0)
                student_ability = random.random()
                
                # Choose a random enrollment pattern for this student
                pattern = random.choice(enrollment_patterns["Diploma"])
                primary_course_count, other_course_count = pattern
                
                # Select courses for this student
                selected_primary_courses = random.sample(diploma_courses, primary_course_count)
                selected_other_courses = []
                if other_course_count > 0:
                    selected_other_courses = random.sample(bachelor_courses, other_course_count)
                
                all_selected_courses = selected_primary_courses + selected_other_courses
                
                # Enroll student in courses and generate marks
                for course in all_selected_courses:
                    # Insert course enrollment
                    cursor.execute(
                        "INSERT IGNORE INTO CourseEnrollments (student_id, course_code) VALUES (%s, %s)",
                        (student_id, course['code'])
                    )
                    
                    # Assign course difficulty (0.0 to 1.0) - higher level courses generally more difficult
                    base_difficulty = 0.4 if course['level'] == "Diploma" else 0.7
                    course_difficulty = base_difficulty + random.uniform(-0.2, 0.2)
                    course_difficulty = max(0.1, min(1.0, course_difficulty))
                    
                    # Generate marks
                    test1, midterm, test2, assignment = generate_marks(student_ability, course_difficulty)
                    
                    # Calculate total and grade
                    total = test1 + midterm + test2 + assignment
                    
                    # Determine grade based on total
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
            
            # Similar enrollment process for bachelor students
            for student_id in bachelor_students:
                student_ability = random.random()
                
                pattern = random.choice(enrollment_patterns["Bachelor"])
                primary_course_count, other_course_count = pattern
                
                selected_primary_courses = random.sample(bachelor_courses, primary_course_count)
                selected_other_courses = []
                if other_course_count > 0:
                    selected_other_courses = random.sample(diploma_courses, other_course_count)
                
                all_selected_courses = selected_primary_courses + selected_other_courses
                
                for course in all_selected_courses:
                    cursor.execute(
                        "INSERT IGNORE INTO CourseEnrollments (student_id, course_code) VALUES (%s, %s)",
                        (student_id, course['code'])
                    )
                    
                    base_difficulty = 0.4 if course['level'] == "Diploma" else 0.7
                    course_difficulty = base_difficulty + random.uniform(-0.2, 0.2)
                    course_difficulty = max(0.1, min(1.0, course_difficulty))
                    
                    test1, midterm, test2, assignment = generate_marks(student_ability, course_difficulty)
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
            
            print(f"Generated and inserted course enrollments and marks for all {TOTAL_STUDENTS} students")
            
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
                    COUNT(*) as student_count
                FROM 
                    Students
                GROUP BY 
                    academic_level
            """)
            levels = cursor.fetchall()
            for level in levels:
                print(f"{level['academic_level']}: {level['student_count']} students")
            
            # Course enrollment statistics
            print("\n=== Course Enrollment Statistics ===")
            cursor.execute("""
                SELECT 
                    c.course_code,
                    c.course_name,
                    c.academic_level,
                    COUNT(ce.student_id) as enrollment_count,
                    ROUND(AVG(sm.total), 2) as avg_total
                FROM 
                    Courses c
                    LEFT JOIN CourseEnrollments ce ON c.course_code = ce.course_code
                    LEFT JOIN StudentMarks sm ON c.course_code = sm.course_code
                GROUP BY 
                    c.course_code
                ORDER BY 
                    c.academic_level, c.course_code
            """)
            courses = cursor.fetchall()
            for course in courses:
                print(f"{course['course_code']} - {course['course_name']} ({course['academic_level']}):")
                print(f"  Enrollments: {course['enrollment_count']}")
                print(f"  Avg Total: {course['avg_total']}")
                print()
            
            # Students with courses from multiple levels
            print("\n=== Students Taking Courses Across Multiple Levels ===")
            cursor.execute("""
                SELECT 
                    s.student_id,
                    s.academic_level as primary_level,
                    COUNT(DISTINCT c.academic_level) as level_count,
                    GROUP_CONCAT(DISTINCT c.academic_level) as levels_enrolled
                FROM 
                    Students s
                    JOIN CourseEnrollments ce ON s.student_id = ce.student_id
                    JOIN Courses c ON ce.course_code = c.course_code
                GROUP BY 
                    s.student_id
                HAVING 
                    level_count > 1
                ORDER BY 
                    s.academic_level, s.student_id
            """)
            multi_level_students = cursor.fetchall()
            print(f"Total students taking courses across multiple levels: {len(multi_level_students)}")
            for student in multi_level_students[:10]:  # Show just first 10 for brevity
                print(f"Student {student['student_id']} (Primary: {student['primary_level']}):")
                print(f"  Taking courses from: {student['levels_enrolled']}")
            
            # Course load distribution
            print("\n=== Student Course Load Distribution ===")
            cursor.execute("""
                SELECT 
                    course_count,
                    COUNT(*) as student_count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Students), 2) as percentage
                FROM (
                    SELECT 
                        student_id, 
                        COUNT(course_code) as course_count
                    FROM 
                        CourseEnrollments
                    GROUP BY 
                        student_id
                ) as course_loads
                GROUP BY 
                    course_count
                ORDER BY 
                    course_count
            """)
            course_loads = cursor.fetchall()
            for load in course_loads:
                print(f"{load['course_count']} courses: {load['student_count']} students ({load['percentage']}%)")
            
    except Error as e:
        print(f"Error: {e}")
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def main():
    print("Starting EEE Department Realistic Data Generation...")
    
    # Step 1: Create database and tables
    if not create_database_and_tables():
        print("Failed to create database structure. Exiting...")
        return
    
    # Step 2: Insert course data
    insert_course_data()
    
    # Step 3: Generate and insert realistic student data
    generate_and_insert_realistic_student_data()
    
    # Step 4: Display summary information
    run_summary_queries()
    
    print("\nRealistic database setup and data generation completed successfully!")
    print(f"Database: {mysql_config['database']}")
    print(f"Total Students: {TOTAL_STUDENTS}")
    print("Students have realistic distribution across academic levels and varying course loads")

if __name__ == "__main__":
    main() 