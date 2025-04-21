import sys
import json
import mysql.connector
from mysql.connector import Error

# Import config (database connection details)
try:
    import config
except ImportError:
    print("Error: Configuration file config.py not found.")
    sys.exit(1)

def get_student_info(student_id):
    """Get basic information about the student"""
    try:
        conn = mysql.connector.connect(**config.DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT student_id, academic_level 
                FROM Students 
                WHERE student_id = %s
            """, (student_id,))
            student = cursor.fetchone()
            if not student:
                return None
            return student
    except Error as e:
        print(f"Database error: {e}")
        return None
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

def get_student_courses(student_id):
    """Get all courses the student is enrolled in"""
    courses = []
    try:
        conn = mysql.connector.connect(**config.DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT ce.course_code, c.course_name, c.academic_level
                FROM CourseEnrollments ce
                JOIN Courses c ON ce.course_code = c.course_code
                WHERE ce.student_id = %s
                ORDER BY ce.course_code
            """, (student_id,))
            courses = cursor.fetchall()
    except Error as e:
        print(f"Database error: {e}")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
    return courses

def get_exam_schedule():
    """Load the exam schedule from the JSON file"""
    try:
        with open('process/schedule_result.json', 'r') as f:
            schedule_data = json.load(f)
            
        # Check if we have a valid schedule
        if 'suggestion' not in schedule_data:
            print("Error: No valid exam schedule found. Please generate a schedule first.")
            return None
            
        # Parse the suggestion text into a dictionary
        suggestion = schedule_data['suggestion']
        exam_schedule = {}
        
        # Parse lines like "1. 2024-08-15: EEE101" or "2. 2024-08-16: Study Day"
        for line in suggestion.split('\n'):
            if ':' in line:
                # Extract date and course code
                parts = line.split(':', 1)
                if len(parts) == 2:
                    # Remove any numbering and whitespace
                    date_part = parts[0].strip()
                    if '.' in date_part:
                        date_part = date_part.split('.', 1)[1].strip()
                    
                    course_part = parts[1].strip()
                    
                    # Only add actual exam dates (not study days)
                    if course_part != "Study Day":
                        exam_schedule[course_part] = date_part
        
        return exam_schedule
    except FileNotFoundError:
        print("Error: Schedule file not found. Please generate a schedule first.")
        return None
    except json.JSONDecodeError:
        print("Error: Invalid JSON format in schedule file.")
        return None
    except Exception as e:
        print(f"Error loading schedule: {e}")
        return None

def display_student_exam_schedule(student_id):
    """Display the exam schedule for a specific student"""
    # Get student information
    student = get_student_info(student_id)
    if not student:
        print(f"Error: Student with ID '{student_id}' not found.")
        return False
    
    # Get courses the student is enrolled in
    courses = get_student_courses(student_id)
    if not courses:
        print(f"No course enrollments found for student {student_id}.")
        return False
    
    # Get the exam schedule
    exam_schedule = get_exam_schedule()
    if not exam_schedule:
        return False
    
    # Create the personalized schedule
    print("\n" + "="*60)
    print(f"EXAM SCHEDULE FOR STUDENT: {student_id} ({student['academic_level']})")
    print("="*60)
    
    # Match enrolled courses with exam dates
    student_exams = []
    for course in courses:
        course_code = course['course_code']
        if course_code in exam_schedule:
            student_exams.append({
                'date': exam_schedule[course_code],
                'course_code': course_code,
                'course_name': course['course_name'],
                'level': course['academic_level']
            })
        else:
            print(f"Note: No exam date found for {course_code} - {course['course_name']}")
    
    # Sort by date
    student_exams.sort(key=lambda x: x['date'])
    
    if student_exams:
        print("\nYour Exam Schedule:")
        print("-"*60)
        print(f"{'Date':<12} | {'Course Code':<10} | {'Course Name':<30} | {'Level'}")
        print("-"*60)
        for exam in student_exams:
            print(f"{exam['date']:<12} | {exam['course_code']:<10} | {exam['course_name']:<30} | {exam['level']}")
    else:
        print("\nNo exams scheduled for your enrolled courses.")
    
    print("\n" + "="*60)
    return True

def main():
    """Main function to handle command line arguments"""
    if len(sys.argv) != 2:
        print("Usage: python student_schedule.py <student_id>")
        return
    
    student_id = sys.argv[1]
    display_student_exam_schedule(student_id)

if __name__ == "__main__":
    main() 