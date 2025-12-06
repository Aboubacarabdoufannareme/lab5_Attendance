// Faculty Intern Dashboard JavaScript Functions - Updated for Real Data

// Global variables
let currentUser = null;

document.addEventListener('DOMContentLoaded', function() {
    // Load user data
    loadUserData();
    
    // Initialize falling leaves
    initFallingLeaves();
    
    // Load dashboard data
    loadDashboardData();
    
    // Set up event listeners
    setupEventListeners();
});

function loadUserData() {
    currentUser = {
        id: document.querySelector('main h2')?.dataset?.userId || '',
        name: document.querySelector('main h2')?.textContent?.replace('Welcome, ', '').replace('!', '') || 'Faculty Intern'
    };
}

function initFallingLeaves() {
    const body = document.querySelector('body');
    for(let i = 0; i < 15; i++) {
        const leaf = document.createElement('div');
        leaf.className = 'leaf';
        leaf.style.left = Math.random() * 100 + 'vw';
        leaf.style.animationDuration = (Math.random() * 5 + 5) + 's';
        leaf.style.animationDelay = Math.random() * 5 + 's';
        leaf.style.opacity = Math.random() * 0.5 + 0.3;
        body.appendChild(leaf);
    }
}

async function loadDashboardData() {
    try {
        // Show loading indicators
        showLoading('#courses-list', 'Loading courses...');
        showLoading('#sessions-list', 'Loading sessions...');
        
        // Fetch data
        const [coursesData, sessionsData, reportsData, auditorsData] = await Promise.all([
            fetchInternCourses(),
            fetchUpcomingSessions(),
            fetchAttendanceReports(),
            fetchPendingAuditors()
        ]);
        
        // Update UI
        updateCoursesList(coursesData);
        updateSessionsList(sessionsData);
        updateReportsTable(reportsData);
        updateAuditorsTable(auditorsData);
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('Failed to load dashboard data');
    }
}

async function fetchInternCourses() {
    try {
        const response = await fetch('api/get_intern_courses.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching courses:', error);
        return [];
    }
}

async function fetchUpcomingSessions() {
    try {
        const response = await fetch('api/get_intern_sessions.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching sessions:', error);
        return [];
    }
}

async function fetchAttendanceReports() {
    try {
        const response = await fetch('api/get_intern_reports.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching reports:', error);
        return [];
    }
}

async function fetchPendingAuditors() {
    try {
        const response = await fetch('api/get_pending_auditors.php');
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error fetching auditors:', error);
        return [];
    }
}

function updateCoursesList(courses) {
    const coursesList = document.querySelector('#courses-list');
    if (!coursesList) return;
    
    coursesList.innerHTML = '';
    
    if (courses.length === 0) {
        coursesList.innerHTML = '<li>No courses available at the moment.</li>';
        return;
    }
    
    courses.forEach(course => {
        const li = document.createElement('li');
        li.innerHTML = `
            <strong>${course.course_code}</strong> - ${course.course_name}
            <br>
            <small>Faculty: ${course.faculty_firstname} ${course.faculty_lastname}</small>
            <button onclick="viewCourse(${course.id}, '${escapeHtml(course.course_name)}')">View</button>
        `;
        coursesList.appendChild(li);
    });
}

function updateSessionsList(sessions) {
    const sessionsList = document.querySelector('#sessions-list');
    if (!sessionsList) return;
    
    sessionsList.innerHTML = '';
    
    if (sessions.length === 0) {
        sessionsList.innerHTML = '<li>No upcoming sessions in the next 7 days.</li>';
        return;
    }
    
    sessions.forEach(session => {
        const sessionDate = new Date(session.session_date);
        const formattedDate = sessionDate.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
        const formattedTime = new Date(`2000-01-01T${session.session_time}`).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
        
        const li = document.createElement('li');
        li.innerHTML = `
            <strong>${session.course_code}</strong> – 
            ${formattedDate} – 
            ${formattedTime}
            ${session.topic ? `<br><small>Topic: ${session.topic}</small>` : ''}
            <button onclick="markAttendance(${session.id}, '${escapeHtml(session.course_name)}', '${session.session_date}')">Mark Attendance</button>
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
                <td colspan="4">No attendance data available.</td>
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
            <td>${(report.recent_notes || '').substring(0, 50)}...</td>
        `;
        tableBody.appendChild(row);
    });
}

function updateAuditorsTable(auditors) {
    const tableBody = document.querySelector('#auditors tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    if (auditors.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4">No pending auditor requests.</td>
            </tr>
        `;
        return;
    }
    
    auditors.forEach(auditor => {
        const requestDate = new Date(auditor.request_date);
        const formattedDate = requestDate.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
        
        const row = document.createElement('tr');
        row.setAttribute('data-request-id', auditor.request_id);
        row.innerHTML = `
            <td>${auditor.firstname} ${auditor.lastname}</td>
            <td>${auditor.course_name}</td>
            <td>${formattedDate}</td>
            <td>
                <button onclick="approveAuditorRequest(${auditor.request_id}, '${escapeHtml(auditor.firstname + ' ' + auditor.lastname)}', '${escapeHtml(auditor.course_name)}')">Approve</button>
                <button onclick="rejectAuditorRequest(${auditor.request_id}, '${escapeHtml(auditor.firstname + ' ' + auditor.lastname)}', '${escapeHtml(auditor.course_name)}')">Reject</button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function viewCourse(courseId, courseName) {
    Swal.fire({
        title: 'Course Details',
        html: `<div style="text-align: left;">
                <p style="font-size: 1.1em;"><strong>📚 Course:</strong> ${courseName}</p>
                <hr style="margin: 15px 0; border-color: #90ee90;">
                <p><strong>📅 Schedule:</strong> Mon, Wed, Fri (10:00 AM - 11:30 AM)</p>
                <p><strong>🏫 Room:</strong> Classroom 302</p>
                <p><strong>👨‍🏫 Instructor:</strong> Dr. Smith</p>
                <p><strong>👥 Students Enrolled:</strong> 45</p>
                <p><strong>📊 Status:</strong> <span style="color: #32cd32;">Active</span></p>
               </div>`,
        icon: 'info',
        confirmButtonText: 'Close',
        background: '#f0fff4',
        color: '#2d5016',
        confirmButtonColor: '#32cd32',
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
        }
    });
}

function markAttendance(sessionId, courseName, sessionDate) {
    // Redirect to attendance page
    window.location.href = `attendance.php?session_id=${sessionId}`;
}

async function approveAuditorRequest(requestId, studentName, courseName) {
    Swal.fire({
        title: '✅ Approve Request?',
        html: `<div style="text-align: center;">
                <p>Approve <strong style="color: #228b22;">${studentName}</strong>'s request to audit:</p>
                <p style="font-size: 1.2em;"><strong>${courseName}</strong></p>
                <div style="background: #f0fff4; padding: 10px; border-radius: 8px; margin: 15px 0; border: 1px solid #90ee90;">
                    <p style="margin: 5px 0;">📧 Student will be notified</p>
                    <p style="margin: 5px 0;">📊 Added to course roster</p>
                    <p style="margin: 5px 0;">👨‍🏫 Faculty notified of new auditor</p>
                </div>
               </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Approve',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#32cd32',
        cancelButtonColor: '#cccccc',
        background: '#f0fff4',
        color: '#2d5016',
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/approve_auditor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        student_name: studentName,
                        course_name: courseName
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: '✅ Request Approved!',
                        html: `<div style="text-align: center;">
                                <p><strong>${studentName}</strong> has been approved to audit:</p>
                                <p style="font-size: 1.2em; color: #228b22;"><strong>${courseName}</strong></p>
                                <div style="background: #f8fff8; padding: 10px; border-radius: 8px; margin: 15px 0; border: 1px solid #90ee90;">
                                    <p style="margin: 5px 0;">✅ Student notified via email</p>
                                    <p style="margin: 5px 0;">✅ Added to course roster</p>
                                    <p style="margin: 5px 0;">✅ Faculty notification sent</p>
                                </div>
                               </div>`,
                        icon: 'success',
                        confirmButtonText: 'Continue',
                        confirmButtonColor: '#32cd32',
                        background: '#f0fff4',
                        showClass: {
                            popup: 'animate__animated animate__bounceIn'
                        }
                    }).then(() => {
                        // Remove the row
                        const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                        if (row) {
                            row.style.transition = 'all 0.5s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(100%)';
                            setTimeout(() => row.remove(), 500);
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to approve request.',
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

async function rejectAuditorRequest(requestId, studentName, courseName) {
    Swal.fire({
        title: '❌ Reject Request?',
        html: `<div style="text-align: center;">
                <p>Reject <strong style="color: #d33;">${studentName}</strong>'s request to audit:</p>
                <p style="font-size: 1.2em;"><strong>${courseName}</strong></p>
                <div style="background: #fff0f0; padding: 10px; border-radius: 8px; margin: 15px 0; border: 1px solid #ff6b6b;">
                    <p style="margin: 5px 0;">📧 Student will be notified</p>
                    <p style="margin: 5px 0;">📝 Can reapply next semester</p>
                    <p style="margin: 5px 0;">💬 Optional rejection reason</p>
                </div>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#32cd32',
        confirmButtonText: 'Yes, Reject',
        cancelButtonText: 'Cancel',
        background: '#f0fff4',
        color: '#2d5016',
        showClass: {
            popup: 'animate__animated animate__fadeInDown'
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const { value: reason } = await Swal.fire({
                title: 'Enter Reason (Optional)',
                input: 'textarea',
                inputPlaceholder: 'Enter reason for rejection...',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#32cd32',
                confirmButtonText: 'Reject',
                cancelButtonText: 'Cancel',
                background: '#f0fff4',
                color: '#2d5016'
            });
            
            if (reason !== undefined) {
                try {
                    const response = await fetch('api/reject_auditor.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            request_id: requestId,
                            student_name: studentName,
                            course_name: courseName,
                            reason: reason
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            title: '❌ Request Rejected',
                            html: `<div style="text-align: center;">
                                    <p><strong>${studentName}</strong>'s request to audit:</p>
                                    <p style="font-size: 1.2em;"><strong>${courseName}</strong></p>
                                    <p>has been rejected.</p>
                                    ${reason ? `<div style="background: #fff0f0; padding: 10px; border-radius: 8px; margin: 15px 0; border: 1px dashed #ff6b6b;">
                                        <p style="margin: 0;"><strong>Reason:</strong> ${reason}</p>
                                    </div>` : ''}
                                   </div>`,
                            icon: 'info',
                            confirmButtonText: 'Continue',
                            confirmButtonColor: '#32cd32',
                            background: '#f0fff4',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            }
                        }).then(() => {
                            // Remove the row
                            const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                            if (row) {
                                row.style.transition = 'all 0.5s ease';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-100%)';
                                setTimeout(() => row.remove(), 500);
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to reject request.',
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
        }
    });
}

// Helper functions
function showLoading(selector, message) {
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

function setupEventListeners() {
    // Add click animations to all buttons
    document.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', function(e) {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Add table row hover effects
    document.querySelectorAll('table tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s ease';
        });
    });
}

// Attendance Functions for Faculty Intern Dashboard

let currentSessionId = null;
let attendanceData = {};

function openAttendanceModal(sessionId, courseName, sessionDate) {
    currentSessionId = sessionId;
    
    // Show loading modal
    Swal.fire({
        title: 'Loading...',
        text: 'Fetching attendance data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch session details and students
    fetch(`api/get_session_for_attendance.php?session_id=${sessionId}`)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            
            if (data.success) {
                showAttendanceModal(data.data);
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to load attendance data',
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'Network error: ' + error.message,
                icon: 'error'
            });
        });
}

function showAttendanceModal(data) {
    const { session, students } = data;
    
    // Create modal HTML
    const modalHTML = `
        <div class="attendance-modal" id="attendanceModal">
            <div class="attendance-modal-content">
                <div class="modal-header">
                    <h3>📝 Mark Attendance - ${session.course_name}</h3>
                    <button class="close-modal" onclick="closeAttendanceModal()">×</button>
                </div>
                
                <div class="modal-body">
                    <div style="padding: 20px 30px; background: #f8fff8; border-bottom: 1px solid #dee2e6;">
                        <p><strong>Date:</strong> ${new Date(session.session_date).toLocaleDateString('en-US', { 
                            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
                        })}</p>
                        <p><strong>Time:</strong> ${session.session_time ? new Date('2000-01-01T' + session.session_time).toLocaleTimeString('en-US', {
                            hour: 'numeric', minute: '2-digit'
                        }) : 'N/A'}</p>
                        ${session.topic ? `<p><strong>Topic:</strong> ${session.topic}</p>` : ''}
                        ${session.location ? `<p><strong>Location:</strong> ${session.location}</p>` : ''}
                    </div>
                    
                    <div style="padding: 20px 30px;">
                        <div class="quick-actions">
                            <button class="action-btn" onclick="markAllAs('present')">
                                ✅ Mark All Present
                            </button>
                            <button class="action-btn" onclick="markAllAs('absent')">
                                ❌ Mark All Absent
                            </button>
                            <button class="action-btn" onclick="resetAll()">
                                🔄 Reset All
                            </button>
                        </div>
                        
                        <div class="attendance-grid">
                            <div class="attendance-header">#</div>
                            <div class="attendance-header">Student Name</div>
                            <div class="attendance-header">Student ID</div>
                            <div class="attendance-header">Status</div>
                            <div class="attendance-header">Notes</div>
                            
                            ${students.map((student, index) => `
                                <div class="attendance-row">
                                    <div>${index + 1}</div>
                                    <div>
                                        <strong>${student.firstname} ${student.lastname}</strong>
                                        ${student.email ? `<br><small style="color: #666;">${student.email}</small>` : ''}
                                    </div>
                                    <div>${student.student_id || student.email || 'N/A'}</div>
                                    <div>
                                        <select class="status-select status-${student.attendance_status || 'absent'}-select"
                                                data-student-id="${student.id}"
                                                onchange="updateAttendanceStatus(${student.id}, this.value)">
                                            <option value="present" ${(student.attendance_status || 'absent') === 'present' ? 'selected' : ''}>Present</option>
                                            <option value="absent" ${(student.attendance_status || 'absent') === 'absent' ? 'selected' : ''}>Absent</option>
                                            <option value="late" ${(student.attendance_status || 'absent') === 'late' ? 'selected' : ''}>Late</option>
                                            <option value="excused" ${(student.attendance_status || 'absent') === 'excused' ? 'selected' : ''}>Excused</option>
                                        </select>
                                    </div>
                                    <div>
                                        <input type="text" 
                                               class="notes-input"
                                               data-student-id="${student.id}"
                                               placeholder="Optional notes..."
                                               value="${student.attendance_notes || ''}"
                                               onchange="updateAttendanceNotes(${student.id}, this.value)">
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button class="cancel-btn" onclick="closeAttendanceModal()">Cancel</button>
                    <button class="save-btn" onclick="saveAttendance()">💾 Save Attendance</button>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Initialize attendance data
    attendanceData = {};
    students.forEach(student => {
        attendanceData[student.id] = {
            status: student.attendance_status || 'absent',
            notes: student.attendance_notes || ''
        };
    });
    
    // Show modal
    document.getElementById('attendanceModal').style.display = 'flex';
}

function closeAttendanceModal() {
    const modal = document.getElementById('attendanceModal');
    if (modal) {
        modal.remove();
    }
    currentSessionId = null;
    attendanceData = {};
}

function updateAttendanceStatus(studentId, status) {
    if (attendanceData[studentId]) {
        attendanceData[studentId].status = status;
    }
    
    // Update select styling
    const select = document.querySelector(`select[data-student-id="${studentId}"]`);
    if (select) {
        select.className = `status-select status-${status}-select`;
    }
}

function updateAttendanceNotes(studentId, notes) {
    if (attendanceData[studentId]) {
        attendanceData[studentId].notes = notes;
    }
}

function markAllAs(status) {
    // Update all selects
    document.querySelectorAll('.status-select').forEach(select => {
        const studentId = select.dataset.studentId;
        select.value = status;
        select.className = `status-select status-${status}-select`;
        
        // Update data
        if (attendanceData[studentId]) {
            attendanceData[studentId].status = status;
        }
    });
}

function resetAll() {
    // Reset all to absent
    markAllAs('absent');
    
    // Clear all notes
    document.querySelectorAll('.notes-input').forEach(input => {
        const studentId = input.dataset.studentId;
        input.value = '';
        
        // Update data
        if (attendanceData[studentId]) {
            attendanceData[studentId].notes = '';
        }
    });
}

async function saveAttendance() {
    if (!currentSessionId) return;
    
    // Show loading
    Swal.fire({
        title: 'Saving...',
        text: 'Saving attendance records',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch('api/save_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_id: currentSessionId,
                attendance: attendanceData
            })
        });
        
        const data = await response.json();
        
        Swal.close();
        
        if (data.success) {
            // Close modal
            closeAttendanceModal();
            
            // Show success message
            Swal.fire({
                title: 'Success!',
                text: 'Attendance saved successfully',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Refresh the sessions table
                updateSessionStatus(currentSessionId);
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to save attendance',
                icon: 'error'
            });
        }
    } catch (error) {
        Swal.close();
        Swal.fire({
            title: 'Error',
            text: 'Network error: ' + error.message,
            icon: 'error'
        });
    }
}

function updateSessionStatus(sessionId) {
    // Update the status badge in the table
    const row = document.querySelector(`tr[data-session-id="${sessionId}"]`);
    if (row) {
        const statusBadge = row.querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.textContent = 'Marked';
            statusBadge.className = 'status-badge status-present';
        }
    }
}

function viewAttendance(sessionId) {
    // Redirect to view attendance page
    window.location.href = `view_attendance.php?session_id=${sessionId}`;
}

function filterSessions() {
    const courseId = document.getElementById('filter_course').value;
    const date = document.getElementById('filter_date').value;
    
    const rows = document.querySelectorAll('#attendance-sessions-table tbody tr');
    
    rows.forEach(row => {
        let show = true;
        
        // Filter by course
        if (courseId && row.dataset.courseId !== courseId) {
            show = false;
        }
        
        // Filter by date
        if (date) {
            const sessionDate = new Date(row.dataset.sessionDate);
            const filterDate = new Date(date);
            
            if (sessionDate.toDateString() !== filterDate.toDateString()) {
                show = false;
            }
        }
        
        // Show/hide row
        row.style.display = show ? '' : 'none';
    });
}

// Add data attributes to session rows for filtering
document.addEventListener('DOMContentLoaded', function() {
    const sessionRows = document.querySelectorAll('#attendance-sessions-table tbody tr');
    sessionRows.forEach(row => {
        const cells = row.cells;
        if (cells.length >= 7) {
            // Extract course ID from button onclick attribute
            const button = cells[6].querySelector('button');
            if (button && button.onclick) {
                const onclickStr = button.onclick.toString();
                const sessionIdMatch = onclickStr.match(/openAttendanceModal\((\d+)/);
                if (sessionIdMatch) {
                    row.dataset.sessionId = sessionIdMatch[1];
                }
            }
        }
    });
});