<?php
require_once 'user_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('admin.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Situation Room - Real-time Collaborative Workshops</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-grey-bg: #f4f4f4;
            --accent-success: #00d084;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            color: var(--ip-dark);
            background: white;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--ip-blue) 0%, #004d6b 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }

        .hero h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.95;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-family: 'Roboto', sans-serif;
        }

        .btn-primary {
            background: white;
            color: var(--ip-blue);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Features Section */
        .features {
            max-width: 1200px;
            margin: 80px auto;
            padding: 0 20px;
        }

        .features h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 36px;
            text-align: center;
            margin-bottom: 60px;
            color: var(--ip-blue);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .feature-card {
            text-align: center;
            padding: 32px;
            background: var(--ip-grey-bg);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .feature-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 20px;
            margin-bottom: 12px;
            color: var(--ip-blue);
        }

        .feature-card p {
            color: #767676;
            line-height: 1.6;
        }

        /* Stats Section */
        .stats {
            background: var(--ip-blue);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .stats-grid {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
        }

        .stat {
            padding: 20px;
        }

        .stat-number {
            font-family: 'Montserrat', sans-serif;
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }

        /* CTA Section */
        .cta {
            max-width: 800px;
            margin: 80px auto;
            padding: 60px 40px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
        }

        .cta h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 32px;
            margin-bottom: 16px;
            color: var(--ip-blue);
        }

        .cta p {
            font-size: 18px;
            color: #767676;
            margin-bottom: 32px;
        }

        /* Footer */
        .footer {
            background: var(--ip-dark);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .footer p {
            opacity: 0.8;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }

            .hero p {
                font-size: 16px;
            }

            .features h2 {
                font-size: 28px;
            }

            .cta {
                margin: 40px 20px;
                padding: 40px 24px;
            }

            .cta h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <h1>Live Situation Room</h1>
        <p>Real-time collaborative workshops designed for 50+ participants. Collect, moderate, and display ideas instantly with powerful moderation tools and beautiful visualizations.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn btn-primary">Get Started Free</a>
            <a href="login.php" class="btn btn-secondary">Sign In</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <h2>Everything You Need for Interactive Workshops</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>50+ Concurrent Users</h3>
                <p>Tested and optimized for large-scale workshops with dozens of simultaneous participants submitting ideas.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Real-Time Updates</h3>
                <p>Submissions appear instantly on the dashboard. 2-second polling ensures everyone sees the latest content.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3>Powerful Moderation</h3>
                <p>Show/hide entries in real-time. Focus mode to spotlight important contributions. Full control over what participants see.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile-First Design</h3>
                <p>Optimized for smartphones. Participants can submit ideas easily from any device with QR code access.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🎨</div>
                <h3>Fully Customizable</h3>
                <p>Custom categories, colors, icons, and branding. Dark mode included. Make it match your workshop theme.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">💾</div>
                <h3>Auto-Backups</h3>
                <p>Every change is automatically backed up. Export to PDF or CSV. Never lose workshop data.</p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat">
                <div class="stat-number">50+</div>
                <div class="stat-label">Concurrent Participants</div>
            </div>
            <div class="stat">
                <div class="stat-number">2s</div>
                <div class="stat-label">Real-Time Refresh</div>
            </div>
            <div class="stat">
                <div class="stat-number">0</div>
                <div class="stat-label">Setup Required</div>
            </div>
            <div class="stat">
                <div class="stat-number">100%</div>
                <div class="stat-label">Data Safety</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <h2>Ready to Run Your First Workshop?</h2>
        <p>Create your free account and start collecting ideas in minutes. No credit card required.</p>
        <a href="register.php" class="btn btn-primary" style="background: var(--ip-blue); color: white;">Create Account →</a>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>© 2026 Live Situation Room. Built for collaborative workshops.</p>
    </footer>
</body>
</html>
