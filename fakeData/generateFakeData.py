import random
import pandas as pd
import numpy as np
import os
import mysql.connector
from mysql.connector import Error

# Set seed for reproducibility
np.random.seed(42)

# MySQL Connection Parameters - Update these with your MySQL server details
mysql_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Standard XAMPP password is empty
    'database': 'dataUTAS' # Changed to dataUTAS as per previous setup
}

# Define EEE department courses with their codes
eee_courses = [
    {"code": "EEE301", "name": "Circuit Theory"},
    {"code": "EEE315", "name": "Power Electronics"},
    {"code": "EEE327", "name": "Digital Signal Processing"},
    {"code": "EEE333", "name": "Electromagnetic Fields"},
    {"code": "EEE342", "name": "Microprocessor Systems"},
    {"code": "EEE356", "name": "Control Systems"},
    {"code": "EEE371", "name": "Power Systems"},
    {"code": "EEE385", "name": "Communication Systems"},
    {"code": "EEE392", "name": "Digital Electronics"},
    {"code": "EEE403", "name": "VLSI Design"},
    {"code": "EEE418", "name": "Embedded Systems"},
    {"code": "EEE425", "name": "Renewable Energy Systems"}
]

# Number of students to generate for each course
num_students = 40

# Generate student IDs in format 12sXXX25
def generate_student_ids(count):
    ids = set()
    while len(ids) < count:
        middle_part = ''.join(random.choices('0123456789', k=3))
        ids.add(f"12s{middle_part}25")
    return list(ids)

# Generate marks with some variability per course
def generate_marks(count, course_difficulty):
    # Adjust base difficulty factor (0.8 to 1.2)
    difficulty = 0.8 + (course_difficulty * 0.04)
    
    test1 = [min(10, max(0, round(random.normalvariate(7 * difficulty, 1.5), 1))) for _ in range(count)]
    midterm = [min(20, max(0, round(random.normalvariate(14 * difficulty, 3), 1))) for _ in range(count)]
    test2 = [min(10, max(0, round(random.normalvariate(7 * difficulty, 1.5), 1))) for _ in range(count)]
    assignment = [min(10, max(5, round(random.normalvariate(8 * difficulty, 1), 1))) for _ in range(count)]
    
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
            
            # Create Students table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS Students (
                    student_id VARCHAR(10) PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            print("Students table created or already exists")
            
            # Create Courses table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS Courses (
                    course_code VARCHAR(10) PRIMARY KEY,
                    course_name VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            print("Courses table created or already exists")
            
            # Create CourseEnrollments table (to track which students are in which courses)
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
            for course in eee_courses:
                cursor.execute(
                    "INSERT IGNORE INTO Courses (course_code, course_name) VALUES (%s, %s)",
                    (course['code'], course['name'])
                )
            
            conn.commit()
            print(f"Inserted {len(eee_courses)} courses into the database")
            
    except Error as e:
        print(f"Error: {e}")
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def generate_and_insert_student_data():
    """Generate student data and insert into the database"""
    try:
        conn = mysql.connector.connect(**mysql_config)
        
        if conn.is_connected():
            cursor = conn.cursor()
            
            # Generate a large pool of unique student IDs (more than we need for all courses)
            all_student_ids = generate_student_ids(num_students * 3)
            
            # Insert all students to the Students table
            for student_id in all_student_ids:
                cursor.execute(
                    "INSERT IGNORE INTO Students (student_id) VALUES (%s)",
                    (student_id,)
                )
            
            conn.commit()
            print(f"Inserted {len(all_student_ids)} students into the database")
            
            # Process each course
            for course in eee_courses:
                # Select random students for this course
                course_students = random.sample(all_student_ids, num_students)
                
                # Generate marks with slight variation based on course
                test1_marks, midterm_marks, test2_marks, assignment_marks = generate_marks(
                    num_students, 
                    course_difficulty=random.uniform(0, 10)  # Different difficulty level for each course
                )
                
                # Insert course enrollments and student marks
                for i, student_id in enumerate(course_students):
                    # Insert course enrollment
                    cursor.execute(
                        "INSERT IGNORE INTO CourseEnrollments (student_id, course_code) VALUES (%s, %s)",
                        (student_id, course['code'])
                    )
                    
                    # Calculate total and grade
                    total = test1_marks[i] + midterm_marks[i] + test2_marks[i] + assignment_marks[i]
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
                            test1_marks[i], 
                            midterm_marks[i], 
                            test2_marks[i], 
                            assignment_marks[i], 
                            total, 
                            grade
                        )
                    )
                
                conn.commit()
                print(f"Processed {num_students} students for {course['code']} - {course['name']}")
            
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
            
            # Course summary statistics
            print("\n=== Course Summary Statistics ===")
            cursor.execute("""
                SELECT 
                    c.course_code,
                    c.course_name,
                    COUNT(sm.student_id) as num_students,
                    ROUND(AVG(sm.total), 2) as avg_total,
                    MAX(sm.total) as max_total,
                    MIN(sm.total) as min_total,
                    SUM(CASE WHEN sm.grade = 'A+' THEN 1 ELSE 0 END) as a_plus_count,
                    SUM(CASE WHEN sm.grade = 'F' THEN 1 ELSE 0 END) as f_count
                FROM 
                    Courses c
                    LEFT JOIN StudentMarks sm ON c.course_code = sm.course_code
                GROUP BY 
                    c.course_code
                ORDER BY 
                    c.course_code
            """)
            courses = cursor.fetchall()
            for course in courses:
                print(f"{course['course_code']} - {course['course_name']}:")
                print(f"  Students: {course['num_students']}")
                print(f"  Avg Total: {course['avg_total']}")
                print(f"  Range: {course['min_total']} - {course['max_total']}")
                print(f"  A+ Count: {course['a_plus_count']}")
                print(f"  F Count: {course['f_count']}")
                print()
            
            # Grade distribution across all courses
            print("=== Overall Grade Distribution ===")
            cursor.execute("""
                SELECT 
                    grade,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM StudentMarks), 2) as percentage
                FROM 
                    StudentMarks
                GROUP BY 
                    grade
                ORDER BY 
                    FIELD(grade, 'A+', 'A', 'B', 'C', 'D', 'F')
            """)
            grades = cursor.fetchall()
            for grade in grades:
                print(f"{grade['grade']}: {grade['count']} students ({grade['percentage']}%)")
            
    except Error as e:
        print(f"Error: {e}")
        
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def main():
    print("Starting EEE Department Database Setup and Data Generation...")
    
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
    
    print("\nDatabase setup and data generation completed successfully!")
    print(f"Database: {mysql_config['database']}")
    print("Tables created: Students, Courses, CourseEnrollments, StudentMarks")

if __name__ == "__main__":
    main()