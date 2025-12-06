// Student Dashboard JavaScript Functions with Real API Calls

document.addEventListener('DOMContentLoaded', function() {
    console.log('Student dashboard loaded with data:', studentData);
    
    // Set up event listeners for dynamic content
    setupEventListeners();
    
    // Refresh data every 5 minutes
    setInterval(refreshDashboardData, 300000);
});

function setupEventListeners() {
    // Event delegation for dynamically created buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-course-btn')) {
            const courseId = e.target.dataset.courseId;
            const courseName = e.target.dataset.courseName;
            viewCourse(courseId, courseName);
        }
        
        if (e.target.classList.contains('auditor-btn')) {
            const courseId = e.target.dataset.courseId;
            const courseName = e.target.dataset.courseName;
            joinAsAuditor(courseId, courseName);
        }
        
        if (e.target.classList.contains('attendance-btn')) {
            const sessionId = e.target.dataset.sessionId;
            const courseName = e.target.dataset.courseName;
            const sessionDate = e.target.dataset.sessionDate;
            markAttendance(sessionId, courseName, sessionDate);
        }
    });
}

async function refreshDashboardData() {
    try {
        const response = await fetch('api/get_student_data.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                updateDashboardUI(data);
                showNotification('Dashboard data refreshed successfully', 'success');
            }
        }
    } catch (error) {
        console.error('Error refreshing dashboard:', error);
    }
}

function updateDashboardUI(data) {
    // Update courses section
    if (data.courses && data.courses.length > 0) {
        updateCoursesList(data.courses);
    }
    
    // Update sessions section
    if (data.sessions && data.sessions.length > 0) {
        updateSessionsList(data.sessions);
    }
    
    // Update attendance section
    if (data.attendance && data.attendance.length > 0) {
        updateAttendanceTable(data.attendance);
    }
}

function updateCoursesList(courses) {
    const container = document.getElementById('courses-container');
    if (!container) return;
    
    const ul = container.querySelector('ul') || document.createElement('ul');
    ul.innerHTML = '';
    
    courses.forEach(course => {
        const li = document.createElement('li');
        li.innerHTML = `
            <strong>${course.course_code} - ${course.course_name}</strong>
            <br>
            <small>Faculty: ${course.faculty_fname} ${course.faculty_lname}</small>
            <br>
            <small>Enrolled: ${new Date(course.enrolled_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</small>
            <div class="course-actions">
                <button class="view-course-btn" data-course-id="${course.id}" data-course-name="${course.course_name}">View Details</button>
                <button class="auditor-btn" data-course-id="${course.id}" data-course-name="${course.course_name}">Join as Auditor</button>
            </div>
        `;
        ul.appendChild(li);
    });
    
    container.innerHTML = '';
    container.appendChild(ul);
}

async function viewCourse(courseId, courseName) {
    try {
        // Fetch detailed course information
        const response = await fetch(`api/get_course_details.php?course_id=${courseId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        let detailsHtml = `<p><strong>Course:</strong> ${courseName}</p>`;
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                detailsHtml += `
                    <p><strong>Course Code:</strong> ${data.course.course_code}</p>
                    <p><strong>Description:</strong> ${data.course.description || 'No description available'}</p>
                    <p><strong>Faculty:</strong> ${data.course.faculty_name}</p>
                    <p><strong>Total Sessions:</strong> ${data.course.total_sessions}</p>
                    <p><strong>Upcoming Sessions:</strong> ${data.course.upcoming_sessions}</p>
                `;
            }
        }
        
        Swal.fire({
            title: 'Course Details',
            html: detailsHtml,
            icon: 'info',
            confirmButtonText: 'Close'
        });
    } catch (error) {
        console.error('Error fetching course details:', error);
        Swal.fire({
            title: 'Course Details',
            html: `<p><strong>Course:</strong> ${courseName}</p>
                   <p>Detailed information could not be loaded at this time.</p>`,
            icon: 'info',
            confirmButtonText: 'Close'
        });
    }
}

async function joinAsAuditor(id, courseName) {
    Swal.fire({
        title: 'Join as Auditor',
        html: `Request to join <strong>${courseName}</strong> as an auditor?<br>
               <small>You will be able to view course materials but may not participate in all activities.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Request',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await fetch('api/request_auditor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        course_id: id,
                        course_name: courseName
                    })
                });
                
                return response.json();
            } catch (error) {
                Swal.showValidationMessage(`Request failed: ${error}`);
                return null;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const data = result.value;
            if (data.success) {
                Swal.fire({
                    title: 'Request Sent!',
                    text: 'Your auditor request has been submitted. You will be notified once it\'s approved.',
                    icon: 'success'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to send request.',
                    icon: 'error'
                });
            }
        }
    });
}

async function markAttendance(sessionId, courseName, sessionDate) {
    // Format the date for display
    const dateObj = new Date(sessionDate);
    const formattedDate = dateObj.toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    Swal.fire({
        title: 'Mark Attendance',
        html: `Mark your attendance for:<br>
               <strong>${courseName}</strong><br>
               Date: ${formattedDate}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Mark Present',
        cancelButtonText: 'Mark Absent',
        showDenyButton: true,
        denyButtonText: 'Mark Late',
        showLoaderOnConfirm: true,
        preConfirm: (status) => {
            const attendanceStatus = status === 'confirm' ? 'present' : (status === 'deny' ? 'late' : 'absent');
            
            return fetch('api/mark_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    course_name: courseName,
                    session_date: sessionDate,
                    status: attendanceStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to mark attendance');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message}`);
                return null;
            });
        }
    }).then((result) => {
        if (result.isConfirmed || result.isDenied || result.dismiss === Swal.DismissReason.cancel) {
            let status, statusText;
            
            if (result.isConfirmed) {
                status = 'present';
                statusText = 'Present';
            } else if (result.isDenied) {
                status = 'late';
                statusText = 'Late';
            } else {
                status = 'absent';
                statusText = 'Absent';
            }
            
            Swal.fire({
                title: 'Attendance Marked!',
                text: `Your attendance has been recorded as ${statusText}.`,
                icon: 'success'
            }).then(() => {
                // Refresh the page to update attendance status
                location.reload();
            });
        }
    });
}

function showNotification(message, type = 'info') {
    const toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    toast.fire({
        icon: type,
        title: message
    });
}

// Utility function to format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}