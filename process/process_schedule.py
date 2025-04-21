import sys
import json
from datetime import date, timedelta
from collections import defaultdict # Import defaultdict
import mysql.connector
from mysql.connector import Error
import requests # Requires installation: pip install requests

# Import config (assuming config.py is in the same directory or Python path)
try:
    import config
except ImportError:
    print(json.dumps({"error": "Configuration file config.py not found."}))
    sys.exit(1)

# Database connection configuration (assuming config.py has DB_CONFIG dictionary)
DB_CONFIG = getattr(config, 'DB_CONFIG', None)
if not DB_CONFIG:
    print(json.dumps({"error": "DB_CONFIG not found in config.py."}))
    sys.exit(1)

# DeepSeek API Key
DEEPSEEK_API_KEY = getattr(config, 'DEEPSEEK_API_KEY', None)

def get_available_dates(start_date_str, end_date_str, holidays_str):
    """Calculates available exam dates, excluding Fridays and provided holidays."""
    available_dates = []
    try:
        start_date = date.fromisoformat(start_date_str)
        end_date = date.fromisoformat(end_date_str)
        
        # Parse holidays (comma-separated YYYY-MM-DD string)
        holidays = set()
        if holidays_str:
            holiday_list = holidays_str.split(',')
            for h_str in holiday_list:
                try:
                    holidays.add(date.fromisoformat(h_str.strip()))
                except ValueError:
                    pass # Ignore invalid holiday formats silently for now

        current_date = start_date
        while current_date <= end_date:
            # Exclude Fridays (weekday=4) and holidays
            if current_date.weekday() != 4 and current_date not in holidays:
                available_dates.append(current_date.isoformat())
            current_date += timedelta(days=1)
            
    except ValueError:
        return {"error": "Invalid date format. Please use YYYY-MM-DD."}
    except Exception as e:
         return {"error": f"Error calculating dates: {str(e)}"}

    return {"dates": available_dates}

def get_course_marks_summary():
    """Fetches average marks per course from the database."""
    summary = {}
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT 
                    c.course_code, 
                    c.course_name,
                    c.academic_level,
                    COUNT(ce.student_id) AS enrollment_count,
                    ROUND(AVG(sm.total), 1) as average_total 
                FROM Courses c
                LEFT JOIN StudentMarks sm ON c.course_code = sm.course_code
                LEFT JOIN CourseEnrollments ce ON c.course_code = ce.course_code
                GROUP BY c.course_code, c.course_name, c.academic_level
                ORDER BY average_total ASC; 
            """) # Order by ASCENDING average total (hardest first)
            results = cursor.fetchall()
            for row in results:
                 # Store more info: average, level, name, enrollment count
                 summary[row['course_code']] = {
                     'average_total': row['average_total'],
                     'level': row['academic_level'],
                     'name': row['course_name'],
                     'enrollment': row['enrollment_count'] if row['enrollment_count'] else 0
                 }
            
    except Error as e:
        return {"error": f"Database error fetching course summary: {e}"}
    finally:
        if cursor: cursor.close()
        if conn and conn.is_connected(): conn.close()
            
    if not summary:
         return {"error": "No course or marks data found in the database."}
         
    return {"summary": summary}

def get_student_enrollments():
    """Fetches student enrollment data: {student_id: [course_code1, course_code2]}"""
    enrollments = defaultdict(list)
    conn = None
    cursor = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT student_id, course_code FROM CourseEnrollments")
            results = cursor.fetchall()
            for row in results:
                enrollments[row['student_id']].append(row['course_code'])
    except Error as e:
        return {"error": f"Database error fetching enrollments: {e}"}
    finally:
        if cursor: cursor.close()
        if conn and conn.is_connected(): conn.close()

    if not enrollments:
         return {"error": "No student enrollment data found."}
         
    return {"enrollments": dict(enrollments)} # Convert back to dict for JSON later if needed

def find_conflicting_courses(enrollments):
    """Identifies pairs of courses that share at least one student."""
    course_pairs = defaultdict(set) # {course_code: {student1, student2}}
    for student_id, courses in enrollments.items():
        for course_code in courses:
            course_pairs[course_code].add(student_id)

    conflicts = set()
    course_list = list(course_pairs.keys())

    for i in range(len(course_list)):
        for j in range(i + 1, len(course_list)):
            course1 = course_list[i]
            course2 = course_list[j]
            # Check for intersection of student sets
            if course_pairs[course1].intersection(course_pairs[course2]):
                # Add the pair in a consistent order (alphabetical) to avoid duplicates
                conflicts.add(tuple(sorted((course1, course2))))
                
    # Convert set of tuples to list of strings for the prompt
    return {"conflicts": [f"{c1} & {c2}" for c1, c2 in conflicts]}

def get_deepseek_suggestion(available_dates, course_summary, conflicts):
    """Calls the DeepSeek API to get a schedule suggestion, considering conflicts."""
    if not DEEPSEEK_API_KEY or DEEPSEEK_API_KEY == "YOUR_DEEPSEEK_API_KEY":
        return {"error": "DeepSeek API key not configured in config.py."}

    # --- Construct the Prompt ---
    start_date = available_dates[0]
    end_date = available_dates[-1]
    num_courses = len(course_summary)
    num_available_days = len(available_dates)
    
    prompt = f"Create a final exam schedule for {num_courses} university courses within the period {start_date} to {end_date}. "
    prompt += f"Available dates for scheduling (excluding Fridays and holidays) are: {', '.join(available_dates)}.\n\n"
    
    prompt += "COURSE DETAILS (Harder courses listed first based on average score):\n"
    # course_summary is already sorted by average score (ASC) from the query
    for course_code, details in course_summary.items():
        prompt += f"- {course_code} ({details['level']}, Avg: {details['average_total'] if details['average_total'] is not None else 'N/A'}, Enrolled: {details['enrollment']})\n"
    
    prompt += f"\nSCHEDULING REQUIREMENTS (MUST FOLLOW):\n"
    prompt += f"1. MULTIPLE EXAMS PER DAY ARE ALLOWED AND ENCOURAGED: Place multiple exams on the same day, especially if they are from different academic levels (Diploma, Advanced Diploma, Bachelor), to make efficient use of the exam period.\n"
    prompt += f"2. AVOID STUDENT CONFLICTS: The following pairs of courses CANNOT be scheduled on the SAME DAY because students are enrolled in both. Respect ALL of these constraints:\n"
    if conflicts:
        prompt += "   - " + "\n   - ".join(conflicts) + "\n"
    else:
        prompt += "   - (No direct student conflicts identified across course pairs, but still aim to separate courses logically).\n"
    prompt += f"3. DISTRIBUTE EXAMS THROUGHOUT THE ENTIRE PERIOD: Ensure exams are scheduled from {start_date} up to {end_date}. The last exam day should be close to {end_date}.\n"
    prompt += f"4. BALANCE DIFFICULTY: Generally, schedule harder courses (lower average scores) earlier in the period or on days with fewer other exams. Use Study Days strategically.\n"
    
    # Study days logic - place remaining available days as study days
    #num_study_days = max(0, num_available_days - num_courses) # Note: This might underestimate study days if many exams are doubled up. We'll let the AI decide where to put them.
    #prompt += f"5. USE STUDY DAYS: Assign any available dates not used for exams as 'Study Day'. Distribute these {num_study_days if num_study_days > 0 else 'few (if any)'} study days to provide breaks, especially around harder exams.\n"
    prompt += f"5. ASSIGN ALL AVAILABLE DATES: Every date listed as available must appear in the final schedule. If no exam is scheduled for a particular available date, simply list the date with no course assigned after the colon.\n"
    
    prompt += f"\nIMPORTANT FORMAT INSTRUCTIONS:\n"
    prompt += f"Provide the final schedule ONLY as a numbered list. Each line MUST be in the format 'YYYY-MM-DD: CourseCode1' or 'YYYY-MM-DD: CourseCode1, CourseCode2' (if multiple exams on that day, comma-separated) or 'YYYY-MM-DD:' (if no exam is scheduled for that available date). List ALL available dates."

    # --- DeepSeek API Call ---
    api_url = "https://api.deepseek.com/v1/chat/completions" 
    headers = {
        "Authorization": f"Bearer {DEEPSEEK_API_KEY}",
        "Content-Type": "application/json"
    }
    payload = {
        "model": "deepseek-chat", 
        "messages": [
            {"role": "system", "content": "You are an AI assistant expert at creating optimized university final exam schedules based on course difficulty, student conflicts, and date constraints. You follow formatting instructions precisely."},
            {"role": "user", "content": prompt}
        ],
        "max_tokens": 1000, # Increased max_tokens slightly for potentially longer schedule
        "temperature": 0.4, # Slightly lower temperature for more deterministic schedule
        "top_p": 0.9
    }

    try:
        response = requests.post(api_url, headers=headers, json=payload, timeout=45) # Increased timeout
        response.raise_for_status() 
        
        api_result = response.json()
        
        if api_result.get('choices') and len(api_result['choices']) > 0:
             suggestion = api_result['choices'][0].get('message', {}).get('content')
             if suggestion:
                  # Basic validation: Check if output seems to follow the date format somewhat
                  if "YYYY-MM-DD" in prompt and not re.search(r'\d{4}-\d{2}-\d{2}:', suggestion):
                       return {"error": "API response received, but doesn't seem to contain the expected date format. Raw response: " + suggestion}
                  return {"suggestion": suggestion.strip()}
             else:
                  return {"error": "Received an empty suggestion from DeepSeek."}
        else:
             # Log the full response for debugging if parsing fails
             # print(json.dumps({"error": "Unexpected API response structure", "details": api_result}))
             return {"error": f"Unexpected API response structure from DeepSeek. Details: {json.dumps(api_result)}"}

    except requests.exceptions.Timeout:
         return {"error": "API request timed out. The scheduling task might be too complex or the API is slow."}
    except requests.exceptions.RequestException as e:
        return {"error": f"API request failed: {e}"}
    except Exception as e:
        # Log the full error for debugging
        import traceback
        tb_str = traceback.format_exc()
        return {"error": f"An unexpected error occurred during API processing: {e}. Traceback: {tb_str}"}


if __name__ == "__main__":
    import re # Import re for basic validation
    RESULT_FILE = 'process/schedule_result.json' 

    if len(sys.argv) < 3:
        result = {"error": "Usage: python process_schedule.py <start_date> <end_date> [holidays_comma_separated]"}
        print(json.dumps(result)) 
        try:
            with open(RESULT_FILE, 'w') as f: json.dump(result, f)
        except IOError as e: print(f"Error writing error to {RESULT_FILE}: {e}")
        sys.exit(1)
        
    start_arg = sys.argv[1]
    end_arg = sys.argv[2]
    holidays_arg = sys.argv[3] if len(sys.argv) > 3 else ""

    # --- Pipeline ---
    # 1. Calculate available dates
    date_result = get_available_dates(start_arg, end_arg, holidays_arg)
    if "error" in date_result:
        result = date_result
    else:
        available_dates = date_result["dates"]
        if not available_dates:
            result = {"error": "No available exam dates found in the specified range after excluding weekends and holidays."}
        else:
            # 2. Get course marks summary (includes level, name, enrollment)
            summary_result = get_course_marks_summary()
            if "error" in summary_result:
                result = summary_result
            else:
                course_summary = summary_result["summary"]
                
                # 3. Get student enrollments
                enrollment_result = get_student_enrollments()
                if "error" in enrollment_result:
                    result = enrollment_result
                else:
                    student_enrollments = enrollment_result["enrollments"]
                    
                    # 4. Find conflicting course pairs
                    conflict_result = find_conflicting_courses(student_enrollments)
                    # Note: Even if there's an error finding conflicts, we might proceed, 
                    # but the schedule won't explicitly avoid them. Handle error? For now, pass empty list if error.
                    conflicts = conflict_result.get("conflicts", []) 
                    if "error" in conflict_result:
                         print(f"Warning: Could not determine conflicting courses: {conflict_result['error']}")

                    # 5. Get suggestion from DeepSeek API (pass conflicts)
                    result = get_deepseek_suggestion(available_dates, course_summary, conflicts)

    # --- Save Result ---
    try:
        with open(RESULT_FILE, 'w') as f:
            json.dump(result, f, indent=4) 
        print(f"Result saved to {RESULT_FILE}") 
    except IOError as e:
        print(f"Error: Could not write result to {RESULT_FILE}: {e}")
        print("\nResult JSON:\n" + json.dumps(result))
    except Exception as e:
        print(f"An unexpected error occurred during file writing: {e}")
        print("\nResult JSON:\n" + json.dumps(result)) 