<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About SmartCard - Student Academic Performance Management System</title>
    <link rel="icon" type="image/png" href="img/logo.png" />
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #2980b9;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .container {
            width: 85%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 0;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        h2 {
            color: var(--primary-color);
            margin: 1.5rem 0 1rem;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 0.5rem;
        }
        
        h3 {
            color: var(--dark-color);
            margin: 1.2rem 0 0.5rem;
        }
        
        p {
            margin-bottom: 1rem;
        }
        
        .team-members {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            margin: 1.5rem 0;
        }
        
        .member {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin: 1rem;
            width: 250px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .member:hover {
            transform: translateY(-5px);
        }
        
        .member h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .member p {
            color: var(--dark-color);
            font-style: italic;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .problem-list, .benefits-list {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .benefits-list li {
            margin-bottom: 0.5rem;
        }
        
        .highlight {
            background-color: var(--light-color);
            padding: 1.5rem;
            border-left: 4px solid var(--secondary-color);
            margin: 1.5rem 0;
        }
        
        footer {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 1.5rem 0;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            
            .team-members {
                flex-direction: column;
                align-items: center;
            }
            
            .member {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>SmartCard</h1>
            <p>A Student Academic Performance Management System with Report Card Generation</p>
        </div>
    </header>
    
    <div class="container">
        <section class="card">
            <h2>About the Project</h2>
            <p>SmartCard is an innovative web-based system designed to streamline academic record management and automate report card generation for educational institutions. Developed specifically to address the challenges faced by Balaytigue National High School, this system transforms traditional manual grading processes into efficient digital workflows.</p>
            
            <div class="highlight">
                <p>The system automates grade computation, maintains comprehensive student records including attendance, learner observed values, awards, and quarterly reports, ensuring all data is accurate and updated in real time.</p>
            </div>
        </section>
        
        <section class="card">
            <h2>Development Team</h2>
            <div class="team-members">
                <div class="member">
                    <h4>Greg Tomco</h4>
                    <p>Project Lead</p>
                </div>
                <div class="member">
                    <h4>Mickaella Angela C. Evangelista</h4>
                    <p>Member</p>
                </div>
                <div class="member">
                    <h4>Princes A. Capacia</h4>
                    <p>Member</p>
                </div>
                <div class="member">
                    <h4>Rovic E. Manalang</h4>
                    <p>Member</p>
                </div>
            </div>
            <p><strong>Degree:</strong> Bachelor of Science in Information Technology</p>
            <p><strong>Year:</strong> 2025</p>
        </section>
        
        <section class="card">
            <h2>Problem Statement</h2>
            <p>Balaytigue National High School mainly uses manual or partly digital grading, leading to:</p>
            <ul class="problem-list">
                <li>Slow record management processes</li>
                <li>Increased likelihood of errors in calculations</li>
                <li>Difficulty in accessing academic data</li>
                <li>Time-consuming report card generation</li>
                <li>Limited real-time access to student performance data</li>
            </ul>
        </section>
        
        <section class="card">
            <h2>System Features</h2>
            <h3>Core Functionalities</h3>
            <ul class="benefits-list">
                <li>Automated grade computation and report card generation</li>
                <li>Comprehensive student record management (attendance, behavior, awards)</li>
                <li>Real-time access to academic records</li>
                <li>Role-based access control (Teachers, Students, Administrators)</li>
                <li>Export capabilities for reports in PDF format</li>
                <li>Secure data storage and backup systems</li>
            </ul>
            
            <h3>Technical Implementation</h3>
            <p>The system is developed using modern web technologies including PHP, JavaScript, HTML, CSS, and MySQL database management system. It follows Agile development methodology to ensure continuous improvement and adaptability to user needs.</p>
        </section>
        
        <section class="card">
            <h2>Benefits to Stakeholders</h2>
            
            <h3>For Balaytigue National High School</h3>
            <p>Streamlines administrative tasks, reduces errors in grade computation, improves accessibility of student records, and decreases delays in releasing grades.</p>
            
            <h3>For Teachers</h3>
            <p>Significantly reduces time and effort spent on administrative tasks, enables effective monitoring of student progress, and provides timely feedback.</p>
            
            <h3>For Students</h3>
            <p>Provides real-time access to grades and academic performance, promotes transparency, and empowers students to track their progress.</p>
            
            <h3>For Parents</h3>
            <p>Improves communication and access to children's academic records, allowing parents to stay informed and communicate efficiently with teachers.</p>
        </section>
        
        <section class="card">
            <h2>Project Scope</h2>
            <p>The SmartCard system is specifically designed for Balaytigue National High School, with features tailored to their academic processes and DepEd requirements. The system handles:</p>
            <ul class="benefits-list">
                <li>Grade computation based on Written Work, Performance Tasks, and Quarterly Assessments</li>
                <li>Generation of report cards (Form 138) and other academic documents</li>
                <li>Student information management with sorting by grade level and section</li>
                <li>Secure storage of historical academic records</li>
                <li>Quarterly academic reports and performance analysis</li>
            </ul>
        </section>
    </div>
    
    <footer>
        <div class="container">
            <p>SmartCard: A Student Academic Performance Management System with Report Card Generation</p>
            <p>Developed for Balaytigue National High School</p>
        </div>
    </footer>
</body>
</html>