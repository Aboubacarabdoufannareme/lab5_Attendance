// Faculty Dashboard JavaScript Functions

// Global variables
let currentUser = null;

document.addEventListener('DOMContentLoaded', function() {
    // Load user data from session
    loadUserData();
    
    // Initialize sparkling effect
    initSparklingEffect();
    
    // Load dynamic data from APIs
    loadDashboardData();
    
    // Set up event listeners
    setupEventListeners();
});

function loadUserData() {
    // You might want to get user info from PHP session or API
    currentUser = {
        id: document.querySelector('main h2')?.dataset?.userId || '',
        name: document.querySelector('main h2')?.textContent?.replace('Welcome, ', '').replace('!', '') || 'Faculty'
    };
}

function initSparklingEffect() {
    const body = document.querySelector('body');
    for(let i = 0; i < 20; i++) {
        const sparkle = document.createElement('div');
        sparkle.className = 'sparkle';
        sparkle.style.left = Math.random() * 100 + 'vw';
        sparkle.style.top = Math.random() * 100 + 'vh';
        sparkle.style.animationDuration = (Math.random() * 3 + 2) + 's';
        sparkle.style.animationDelay = Math.random() * 5 + 's';
        sparkle.style.opacity = Math.random() * 0.7 + 0.3;
        body.appendChild(sparkle);
    }
}

async function loadDashboardData() {
    try {
        // Show loading indicators
        showLoading('#courses-list', 'Loading courses...');
        showLoading('#sessions-list', 'Loading sessions...');
        
        // Fetch real-time data
        const [coursesData, sessionsData, reportsData] = await Promise.all([
            fetchCourses(),
            fetchSessions(),
            fetchReports()
        ]);
        
        // Update UI with real data
        updateCoursesList(coursesData);
        updateSessionsList(sessionsData);
        updateReportsTable(reportsData);
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showError('Failed to load dashboard data. Please refresh the page.');
    }
}

async function fetchCourses() {
    try {
        const response = await fetch('api/get_faculty_courses.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching courses:', error);
        return [];
    }
}

async function fetchSessions() {
    try {
        const response = await fetch('api/get_faculty_sessions.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching sessions:', error);
        return [];
    }
}

async function fetchReports() {
    try {
        const response = await fetch('api/get_faculty_reports.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching reports:', error);
        return [];
    }
}

function updateCoursesList(courses) {
    const coursesList = document.querySelector('#courses-list');
    if (!coursesList) return;
    
    coursesList.innerHTML = '';
    
    if (courses.length === 0) {
        coursesList.innerHTML = '<li>No courses found. Create your first course!</li>';
        return;
    }
    
    courses.forEach(course => {
        const li = document.createElement('li');
        li.innerHTML = `
            <strong>${course.course_code}</strong> - ${course.course_name}
            <br>
            <small>${course.description || 'No description'}</small>
            <button onclick="editCourse(${course.id}, '${escapeHtml(course.course_name)}')">Edit</button>
            <button onclick="deleteCourse(${course.id}, '${escapeHtml(course.course_name)}')">Delete</button>
        `;
        coursesList.appendChild(li);
    });
}

function updateSessionsList(sessions) {
    const sessionsList = document.querySelector('#sessions-list');
    if (!sessionsList) return;
    
    sessionsList.innerHTML = '';
    
    if (sessions.length === 0) {
        sessionsList.innerHTML = '<li>No sessions found. Create your first session!</li>';
        return;
    }
    
    sessions.forEach(session => {
        const sessionDate = new Date(session.session_date);
        const formattedDate = sessionDate.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
        
        const li = document.createElement('li');
        li.innerHTML = `
            <strong>${session.course_name}</strong> – 
            ${formattedDate} – 
            ${session.attendance_count || 0} Students Attended
            ${session.topic ? `<br><small>Topic: ${session.topic}</small>` : ''}
            <button onclick="markAttendance(${session.id})">Mark Attendance</button>
        `;
        sessionsList.appendChild(li);
    });
}

function updateReportsTable(reports) {
    const tableBody = document.querySelector('#reports tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    if (reports.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5">No attendance data available.</td>
            </tr>
        `;
        return;
    }
    
    reports.forEach(report => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${report.firstname} ${report.lastname}</td>
            <td>${report.course_name}</td>
            <td>${report.attendance_percentage}%</td>
            <td>${report.participation}</td>
            <td>
                <button onclick="viewStudentReport(${report.student_id}, '${escapeHtml(report.course_name)}')">Details</button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function showCreateCourseModal() {
    Swal.fire({
        title: 'Create New Course',
        html: `
            <form id="createCourseForm">
                <div style="text-align: left; margin-bottom: 15px;">
                    <label for="course_code" style="display: block; margin-bottom: 5px;">Course Code:</label>
                    <input type="text" id="course_code" class="swal2-input" placeholder="e.g., MATH101" required>
                </div>
                <div style="text-align: left; margin-bottom: 15px;">
                    <label for="course_name" style="display: block; margin-bottom: 5px;">Course Name:</label>
                    <input type="text" id="course_name" class="swal2-input" placeholder="e.g., Mathematics 101" required>
                </div>
                <div style="text-align: left; margin-bottom: 15px;">
                    <label for="course_description" style="display: block; margin-bottom: 5px;">Description:</label>
                    <textarea id="course_description" class="swal2-textarea" placeholder="Course description..." style="width: 100%; min-height: 80px;"></textarea>
                </div>
            </form>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Create Course',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ff4757',
        preConfirm: () => {
            const courseCode = document.getElementById('course_code').value.trim();
            const courseName = document.getElementById('course_name').value.trim();
            const description = document.getElementById('course_description').value.trim();
            
            if (!courseCode || !courseName) {
                Swal.showValidationMessage('Please fill in all required fields');
                return false;
            }
            
            if (courseCode.length > 20) {
                Swal.showValidationMessage('Course code must be 20 characters or less');
                return false;
            }
            
            if (courseName.length > 200) {
                Swal.showValidationMessage('Course name must be 200 characters or less');
                return false;
            }
            
            return {
                course_code: courseCode,
                course_name: courseName,
                description: description
            };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            createCourse(result.value);
        }
    });
}

async function createCourse(courseData) {
    try {
        const response = await fetch('api/create_course.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(courseData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                title: 'Course Created!',
                text: data.message || 'The course has been created successfully.',
                icon: 'success',
                confirmButtonColor: '#ff4757'
            }).then(() => {
                // Refresh the courses list
                loadDashboardData();
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to create course.',
                icon: 'error'
            });
        }
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: 'An error occurred. Please try again.',
            icon: 'error'
        });
    }
}



async function editCourse(id, courseName) {
    try {
        console.log('Attempting to edit course with ID:', id);
        
        // Show loading
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching course details',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const response = await fetch(`api/get_course_simple.php?id=${id}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('API response:', data);
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch course details');
        }
        
        const course = data.data;
        
        // Close loading
        Swal.close();
        
        // Show edit form
        Swal.fire({
            title: 'Edit Course',
            html: `
                <p>Editing: <strong>${course.course_name}</strong></p>
                <form id="editCourseForm">
                    <div style="text-align: left; margin-bottom: 15px;">
                        <label for="edit_course_code" style="display: block; margin-bottom: 5px;">Course Code:</label>
                        <input type="text" id="edit_course_code" class="swal2-input" 
                               value="${course.course_code || ''}" required>
                    </div>
                    <div style="text-align: left; margin-bottom: 15px;">
                        <label for="edit_course_name" style="display: block; margin-bottom: 5px;">Course Name:</label>
                        <input type="text" id="edit_course_name" class="swal2-input" 
                               value="${course.course_name || ''}" required>
                    </div>
                    <div style="text-align: left; margin-bottom: 15px;">
                        <label for="edit_course_description" style="display: block; margin-bottom: 5px;">Description:</label>
                        <textarea id="edit_course_description" class="swal2-textarea" 
                                  style="width: 100%; min-height: 80px;">${course.description || ''}</textarea>
                    </div>
                </form>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ff4757',
            width: '600px',
            preConfirm: () => {
                const courseCode = document.getElementById('edit_course_code').value.trim();
                const newCourseName = document.getElementById('edit_course_name').value.trim();
                const description = document.getElementById('edit_course_description').value.trim();
                
                if (!courseCode || !newCourseName) {
                    Swal.showValidationMessage('Please fill in all required fields');
                    return false;
                }
                
                return {
                    id: id,
                    course_code: courseCode,
                    course_name: newCourseName,
                    description: description
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                updateCourse(result.value);
            }
        });
        
    } catch (error) {
        console.error('Error editing course:', error);
        
        Swal.fire({
            title: 'Error',
            html: `
                <div style="text-align: left;">
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p><strong>Course ID:</strong> ${id}</p>
                    <p><strong>Course Name:</strong> ${courseName}</p>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                        <strong>Quick Fix:</strong>
                        <ol>
                            <li>Make sure course ID ${id} exists</li>
                            <li>Check browser console for more details</li>
                            <li>Try refreshing the page</li>
                        </ol>
                    </div>
                </div>
            `,
            icon: 'error',
            width: '600px'
        });
    }
}

async function deleteCourse(id, courseName) {
    Swal.fire({
        title: 'Delete Course?',
        html: `Are you sure you want to delete <strong>${courseName}</strong>?<br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/delete_course_simple.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error'
                    });
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error',
                    text: 'Network error. Please try again.',
                    icon: 'error'
                });
            }
        }
    });
}

function createSession() {
    // First, load available courses
    fetch('api/get_faculty_courses.php')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load courses');
            }
            
            const courses = data.data || [];
            
            if (courses.length === 0) {
                Swal.fire({
                    title: 'No Courses',
                    text: 'You need to create a course first before creating sessions.',
                    icon: 'warning',
                    confirmButtonColor: '#ff4757'
                });
                return;
            }
            
            // Build HTML for the form
            let coursesOptions = '<option value="">Select a course</option>';
            courses.forEach(course => {
                coursesOptions += `<option value="${course.id}">${course.course_code} - ${course.course_name}</option>`;
            });
            
            // Get tomorrow's date for default
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().split('T')[0];
            
            // Show the create session form
            Swal.fire({
                title: 'Create New Session',
                html: `
                    <form id="createSessionForm">
                        <div style="text-align: left; margin-bottom: 15px;">
                            <label for="session_course" style="display: block; margin-bottom: 5px;">Course:</label>
                            <select id="session_course" class="swal2-input" required>
                                ${coursesOptions}
                            </select>
                        </div>
                        <div style="text-align: left; margin-bottom: 15px;">
                            <label for="session_date" style="display: block; margin-bottom: 5px;">Date:</label>
                            <input type="date" id="session_date" class="swal2-input" 
                                   value="${tomorrowStr}" 
                                   min="${tomorrowStr}" required>
                        </div>
                        <div style="text-align: left; margin-bottom: 15px;">
                            <label for="session_time" style="display: block; margin-bottom: 5px;">Time:</label>
                            <input type="time" id="session_time" class="swal2-input" value="10:00" required>
                        </div>
                        <div style="text-align: left; margin-bottom: 15px;">
                            <label for="session_topic" style="display: block; margin-bottom: 5px;">Topic (Optional):</label>
                            <input type="text" id="session_topic" class="swal2-input" placeholder="e.g., Introduction to Calculus">
                        </div>
                        <div style="text-align: left; margin-bottom: 15px;">
                            <label for="session_location" style="display: block; margin-bottom: 5px;">Location:</label>
                            <input type="text" id="session_location" class="swal2-input" value="Classroom" placeholder="e.g., Room 101">
                        </div>
                    </form>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Create Session',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ff4757',
                width: '600px',
                preConfirm: () => {
                    const courseId = document.getElementById('session_course').value;
                    const sessionDate = document.getElementById('session_date').value;
                    const sessionTime = document.getElementById('session_time').value;
                    const topic = document.getElementById('session_topic').value.trim();
                    const location = document.getElementById('session_location').value.trim();
                    
                    if (!courseId) {
                        Swal.showValidationMessage('Please select a course');
                        return false;
                    }
                    
                    if (!sessionDate) {
                        Swal.showValidationMessage('Please select a date');
                        return false;
                    }
                    
                    if (!sessionTime) {
                        Swal.showValidationMessage('Please select a time');
                        return false;
                    }
                    
                    // Ensure time format
                    const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
                    if (!timePattern.test(sessionTime)) {
                        Swal.showValidationMessage('Please enter a valid time (HH:MM format)');
                        return false;
                    }
                    
                    // Add seconds to time if not present
                    const fullTime = sessionTime.includes(':') ? 
                        (sessionTime.split(':').length === 2 ? sessionTime + ':00' : sessionTime) : 
                        sessionTime + ':00';
                    
                    return {
                        course_id: courseId,
                        session_date: sessionDate,
                        session_time: fullTime,
                        topic: topic,
                        location: location || 'Classroom'
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    createSessionAPI(result.value);
                }
            });
        })
        .catch(error => {
            console.error('Error loading courses:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to load courses. Please try again.',
                icon: 'error'
            });
        });
}

async function createSessionAPI(sessionData) {
    try {
        // Show loading
        Swal.fire({
            title: 'Creating Session...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const response = await fetch('api/create_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(sessionData)
        });
        
        const data = await response.json();
        
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                title: 'Session Created!',
                html: `
                    <div style="text-align: left;">
                        <p>✅ Session created successfully!</p>
                        <p><strong>Course:</strong> ${data.course_name || 'N/A'}</p>
                        <p><strong>Date:</strong> ${sessionData.session_date}</p>
                        <p><strong>Time:</strong> ${sessionData.session_time}</p>
                        ${sessionData.topic ? `<p><strong>Topic:</strong> ${sessionData.topic}</p>` : ''}
                        <p><strong>Location:</strong> ${sessionData.location}</p>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'Continue',
                confirmButtonColor: '#ff4757'
            }).then(() => {
                // Refresh the page to show new session
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to create session.',
                icon: 'error'
            });
        }
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: 'Network error. Please try again.',
            icon: 'error'
        });
    }
}

async function markAttendance(sessionId) {
    try {
        // Show loading
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching attendance data',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const response = await fetch(`api/get_session_attendance.php?session_id=${sessionId}`);
        const data = await response.json();
        
        Swal.close();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load attendance data');
        }
        
        const session = data.data.session;
        const students = data.data.students;
        
        // Format date
        const sessionDate = new Date(session.session_date);
        const formattedDate = sessionDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Build HTML for attendance form
        let studentsHTML = '';
        
        if (students.length === 0) {
            studentsHTML = '<div style="text-align: center; padding: 20px; color: #666;">No students enrolled in this course.</div>';
        } else {
            studentsHTML = `
                <div style="max-height: 400px; overflow-y: auto; margin: 15px 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Student</th>
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            students.forEach((student, index) => {
                studentsHTML += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <strong>${student.firstname} ${student.lastname}</strong><br>
                            <small style="color: #666;">${student.email}</small>
                        </td>
                        <td style="padding: 10px;">
                            <select 
                                class="attendance-status" 
                                data-student-id="${student.id}"
                                style="padding: 8px; border-radius: 4px; border: 1px solid #ddd; width: 100%;"
                            >
                                <option value="present" ${student.attendance_status === 'present' ? 'selected' : ''}>Present</option>
                                <option value="absent" ${student.attendance_status === 'absent' ? 'selected' : ''}>Absent</option>
                                <option value="late" ${student.attendance_status === 'late' ? 'selected' : ''}>Late</option>
                                <option value="excused" ${student.attendance_status === 'excused' ? 'selected' : ''}>Excused</option>
                            </select>
                        </td>
                        <td style="padding: 10px;">
                            <input 
                                type="text" 
                                class="attendance-notes" 
                                data-student-id="${student.id}"
                                placeholder="Optional notes..."
                                value="${student.attendance_notes || ''}"
                                style="padding: 8px; border-radius: 4px; border: 1px solid #ddd; width: 100%;"
                            >
                        </td>
                    </tr>
                `;
            });
            
            studentsHTML += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        // Show attendance modal
        Swal.fire({
            title: 'Mark Attendance',
            html: `
                <div style="text-align: left;">
                    <p><strong>Course:</strong> ${session.course_code} - ${session.course_name}</p>
                    <p><strong>Date:</strong> ${formattedDate}</p>
                    <p><strong>Time:</strong> ${session.session_time || 'N/A'}</p>
                    ${session.topic ? `<p><strong>Topic:</strong> ${session.topic}</p>` : ''}
                    
                    ${studentsHTML}
                </div>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Save Attendance',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ff4757',
            width: '800px',
            preConfirm: () => {
                const attendanceData = {};
                const statusElements = document.querySelectorAll('.attendance-status');
                const notesElements = document.querySelectorAll('.attendance-notes');
                
                statusElements.forEach(element => {
                    const studentId = element.dataset.studentId;
                    attendanceData[studentId] = {
                        status: element.value,
                        notes: ''
                    };
                });
                
                notesElements.forEach(element => {
                    const studentId = element.dataset.studentId;
                    if (attendanceData[studentId]) {
                        attendanceData[studentId].notes = element.value.trim();
                    }
                });
                
                return {
                    session_id: sessionId,
                    attendance: attendanceData
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                saveAttendance(result.value);
            }
        });
        
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: error.message,
            icon: 'error'
        });
    }
}

async function saveAttendance(attendanceData) {
    try {
        const response = await fetch('api/save_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(attendanceData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message || 'Attendance saved successfully',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to save attendance',
                icon: 'error'
            });
        }
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: 'Network error. Please try again.',
            icon: 'error'
        });
    }
}

function viewStudentReport(studentId, courseName) {
    Swal.fire({
        title: 'Student Report',
        html: `
            <p>Viewing report for student in: <strong>${courseName}</strong></p>
            <p>Detailed report will be shown here.</p>
        `,
        icon: 'info',
        confirmButtonText: 'Close',
        confirmButtonColor: '#ff4757'
    });
}

// Helper functions
function showLoading(selector, message = 'Loading...') {
    const element = document.querySelector(selector);
    if (element) {
        element.innerHTML = `<li>${message}</li>`;
    }
}

function showError(message) {
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#ff4757'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + N to create new course
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        showCreateCourseModal();
    }
    
    // Ctrl + S to create new session
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        createSession();
    }
});

// Add SweetAlert toast notifications
if (typeof Swal !== 'undefined') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    

    // Add debug logging
function debugAPI(url, options = {}) {
    console.log(`Calling API: ${url}`);
    console.log('Options:', options);
    
    return fetch(url, options)
        .then(response => {
            console.log(`Response status: ${response.status}`);
            console.log(`Response headers:`, Object.fromEntries(response.headers.entries()));
            return response.text().then(text => {
                console.log(`Response body:`, text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    return { success: false, message: 'Invalid JSON response' };
                }
            });
        })
        .catch(error => {
            console.error('Fetch error:', error);
            throw error;
        });
}

// Update the editCourse function to use debugAPI for testing
async function editCourseDebug(courseId, courseName) {
    try {
        console.log('Editing course ID:', courseId);
        
        // Test the API endpoint directly
        const testUrl = `api/get_course.php?id=${courseId}`;
        console.log('Testing URL:', testUrl);
        
        const data = await debugAPI(testUrl);
        
        if (!data.success) {
            console.error('API Error:', data.message);
            throw new Error(data.message || 'Failed to fetch course details');
        }
        
        // Rest of your code...
    } catch (error) {
        console.error('Full error:', error);
        Swal.fire({
            title: 'Error',
            text: `Failed to load course details: ${error.message}`,
            icon: 'error'
        });
    }
}

    // You can use Toast.fire() for notifications
    window.showToast = function(type, message) {
        Toast.fire({
            icon: type,
            title: message
        });
    };
}