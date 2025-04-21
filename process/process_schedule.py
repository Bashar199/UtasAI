import sys
import json
from datetime import date, timedelta
import mysql.connector
from mysql.connector import Error
import requests # Requires installation: pip install requests

# Import config (assuming config.py is in the same directory or Python path)
try:
    import config
except ImportError:
    print(json.dumps({"error": "Configuration file config.py not found."}))
    sys.exit(1)

def get_available_dates(start_date_str, end_date_str, holidays_str):
    """Calculates available exam dates, excluding weekends and provided holidays."""
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
    try:
        conn = mysql.connector.connect(**config.DB_CONFIG)
        if conn.is_connected():
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT 
                    course_code, 
                    ROUND(AVG(total), 1) as average_total 
                FROM StudentMarks 
                GROUP BY course_code
                ORDER BY average_total DESC; 
            """)
            results = cursor.fetchall()
            for row in results:
                 summary[row['course_code']] = row['average_total']
            
    except Error as e:
        return {"error": f"Database error: {e}"}
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            
    if not summary:
         return {"error": "No student marks data found in the database."}
         
    return {"summary": summary}

def get_deepseek_suggestion(available_dates, course_summary):
    """Calls the DeepSeek API to get a schedule suggestion."""
    api_key = config.DEEPSEEK_API_KEY
    if not api_key or api_key == "YOUR_DEEPSEEK_API_KEY":
        return {"error": "DeepSeek API key not configured in config.py."}

    # --- Construct the Prompt ---
    start_date = available_dates[0]
    end_date = available_dates[-1]
    num_courses = len(course_summary)
    num_available_days = len(available_dates)
    
    # Determine how to divide the courses across the entire period
    first_quarter_date = available_dates[int(num_available_days * 0.25)]
    middle_date = available_dates[int(num_available_days * 0.5)]
    third_quarter_date = available_dates[int(num_available_days * 0.75)]
    
    # Calculate roughly how many courses should be in each quarter
    courses_per_quarter = max(1, num_courses // 4)
    
    prompt = f"Create a final exam schedule for {num_courses} courses within the period {start_date} to {end_date}. "
    prompt += f"The available dates for scheduling (excluding Fridays and holidays) are: {', '.join(available_dates)}.\n"
    prompt += f"Consider the average student performance (lower average suggests a harder course):\n"
    # Sort courses by average score to list harder ones first
    sorted_courses = sorted(course_summary.items(), key=lambda item: item[1])
    for course, avg_mark in sorted_courses:
        prompt += f"- {course}: Average {avg_mark}\n"
    
    prompt += f"\nREQUIREMENTS (MUST FOLLOW):\n"
    prompt += f"1. DISTRIBUTE EXAMS THROUGHOUT THE ENTIRE PERIOD: You must place exams evenly from {start_date} all the way to {end_date}.\n"
    prompt += f"2. EXAMS MUST BE DISTRIBUTED IN ALL TIME PERIODS as follows:\n"
    prompt += f"   - Schedule approximately {courses_per_quarter} exams before {first_quarter_date}\n"
    prompt += f"   - Schedule approximately {courses_per_quarter} exams between {first_quarter_date} and {middle_date}\n"
    prompt += f"   - Schedule approximately {courses_per_quarter} exams between {middle_date} and {third_quarter_date}\n"
    prompt += f"   - Schedule approximately {courses_per_quarter} exams after {third_quarter_date} and through {end_date}\n"
    prompt += f"3. THE LAST EXAM MUST BE SCHEDULED within the last few days of the available period (near {end_date}).\n"
    prompt += f"4. Balance difficulty by placing harder courses (lower averages) with more space around them.\n"
    
    # Study days logic
    num_study_days = max(0, num_available_days - num_courses)
    if num_study_days > 0:
        prompt += f"5. Mark the remaining {num_study_days} days as 'Study Day' and distribute them between exams.\n"
    
    prompt += f"\nIMPORTANT FORMAT INSTRUCTIONS:\n"
    prompt += f"Provide the final schedule ONLY as a numbered list, with each line in the exact format 'YYYY-MM-DD: CourseCode' or 'YYYY-MM-DD: Study Day'. Every available date must be assigned."

    # --- DeepSeek API Call (Example Structure) ---
    api_url = "https://api.deepseek.com/v1/chat/completions" # Example Endpoint
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }
    payload = {
        "model": "deepseek-chat", # Or the specific model you want to use
        "messages": [
            {"role": "system", "content": "You are an assistant helping schedule university exams."},
            {"role": "user", "content": prompt}
        ],
        # Add other parameters like temperature, max_tokens as needed
        "max_tokens": 600,
        "temperature": 0.5 
    }

    try:
        response = requests.post(api_url, headers=headers, json=payload, timeout=30)
        response.raise_for_status() # Raise an exception for bad status codes (4xx or 5xx)
        
        api_result = response.json()
        
        # --- Parse the Response ---
        # This depends heavily on the actual structure of DeepSeek's response
        if api_result.get('choices') and len(api_result['choices']) > 0:
             suggestion = api_result['choices'][0].get('message', {}).get('content')
             if suggestion:
                  return {"suggestion": suggestion.strip()}
             else:
                  return {"error": "Received an empty suggestion from DeepSeek."}
        else:
             # Log the full response for debugging if parsing fails
             # print(json.dumps({"error": "Unexpected API response structure", "details": api_result}))
             return {"error": "Unexpected API response structure from DeepSeek."}

    except requests.exceptions.RequestException as e:
        return {"error": f"API request failed: {e}"}
    except Exception as e:
        return {"error": f"An error occurred during API processing: {e}"}


if __name__ == "__main__":
    # Use raw string (r'...') for Windows paths to avoid issues with backslashes
    RESULT_FILE = 'process/schedule_result.json' # Output file path relative to the project root

    # Get input from command line arguments
    if len(sys.argv) < 3:
        result = {"error": "Usage: python process_schedule.py <start_date> <end_date> [holidays_comma_separated]"}
        print(json.dumps(result)) # Still print usage error to console
        # Save error to file as well
        try:
            with open(RESULT_FILE, 'w') as f:
                json.dump(result, f)
        except IOError as e:
             print(f"Error: Could not write error to {RESULT_FILE}: {e}")
        sys.exit(1)
        
    start_arg = sys.argv[1]
    end_arg = sys.argv[2]
    holidays_arg = sys.argv[3] if len(sys.argv) > 3 else ""

    # 1. Calculate available dates
    date_result = get_available_dates(start_arg, end_arg, holidays_arg)
    if "error" in date_result:
        result = date_result
    else:
        available_dates = date_result["dates"]
        if not available_dates:
            result = {"error": "No available exam dates found in the specified range after excluding weekends and holidays."}
        else:
            # 2. Get course marks summary
            summary_result = get_course_marks_summary()
            if "error" in summary_result:
                result = summary_result
            else:
                course_summary = summary_result["summary"]
                # 3. Get suggestion from DeepSeek API
                result = get_deepseek_suggestion(available_dates, course_summary)

    # Save the final result (suggestion or error) to the JSON file
    try:
        with open(RESULT_FILE, 'w') as f:
            json.dump(result, f, indent=4) # Add indent for readability
        print(f"Result saved to {RESULT_FILE}") # Confirmation message
    except IOError as e:
        print(f"Error: Could not write result to {RESULT_FILE}: {e}")
        # Also print the result to console as fallback if file write fails
        print("\nResult JSON:\n" + json.dumps(result))
    except Exception as e:
        print(f"An unexpected error occurred during file writing: {e}")
        print("\nResult JSON:\n" + json.dumps(result)) 