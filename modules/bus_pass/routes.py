from flask import Blueprint, render_template, request, session, redirect, url_for, flash
from db import get_db

bus_pass = Blueprint('bus_pass', __name__)

# ============================================
# Middleware - Faculty login check
# ============================================
def faculty_required(f):
    from functools import wraps
    @wraps(f)
    def decorated(*args, **kwargs):
        if 'user_id' not in session or session.get('role') not in ['faculty', 'admin']:
            flash('Please login as faculty to access this page.', 'danger')
            return redirect(url_for('home'))
        return f(*args, **kwargs)
    return decorated

# ============================================
# Route 1 - Bus Pass Search Page
# ============================================
@bus_pass.route('/search')
#@faculty_required 
def search_page():
    return render_template('bus_pass/search.html')

# ============================================
# Route 2 - Search Student (by Name or ID)
# ============================================
@bus_pass.route('/search', methods=['POST'])
#@faculty_required
def search_student():
    query = request.form.get('query', '').strip()

    if not query:
        flash('Please enter a student name or ID.', 'warning')
        return redirect(url_for('bus_pass.search_page'))

    db = get_db()
    cursor = db.cursor()

    # Search by student_id OR name (case insensitive)
    cursor.execute("""
        SELECT s.*, b.pass_number, b.route_no, b.issue_date, b.expiry_date, b.is_active
        FROM students s
        LEFT JOIN bus_passes b ON s.student_id = b.student_id
        WHERE s.student_id = %s OR s.name LIKE %s
    """, (query, f'%{query}%'))

    results = cursor.fetchall()
    cursor.close()

    if not results:
        flash(f'No student found for: "{query}"', 'warning')
        return render_template('bus_pass/search.html', results=[], query=query)

    return render_template('bus_pass/search.html', results=results, query=query)

# ============================================
# Route 3 - Student Detail Page
# ============================================
@bus_pass.route('/student/<student_id>')
#@faculty_required
def student_detail(student_id):
    db = get_db()
    cursor = db.cursor()

    # Get student details
    cursor.execute("""
        SELECT s.*, b.pass_number, b.route_no, b.issue_date, b.expiry_date, b.is_active
        FROM students s
        LEFT JOIN bus_passes b ON s.student_id = b.student_id
        WHERE s.student_id = %s
    """, (student_id,))

    student = cursor.fetchone()
    cursor.close()

    if not student:
        flash('Student not found.', 'danger')
        return redirect(url_for('bus_pass.search_page'))

    return render_template('bus_pass/student_detail.html', student=student)
