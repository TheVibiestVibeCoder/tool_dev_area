<?php
require_once 'user_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('admin.php');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Situation Room - Welcome</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js"
            }
        }
    </script>

    <style>
        /* --- DASHBOARD THEME VARIABLES --- */
        :root {
            /* Corporate Colors */
            --ip-blue: #00658b;
            --ip-dark: #32373c;
            --ip-grey-bg: #f4f4f4;
            --ip-card-bg: #ffffff;
            --ip-border: #e0e0e0;
            
            /* Text Colors */
            --text-main: #32373c;
            --text-muted: #767676;
            --text-light: #ffffff;
            
            /* Shadows & Effects */
            --card-shadow: 0 2px 5px rgba(0,0,0,0.05);
            --card-shadow-hover: 0 10px 20px rgba(0,0,0,0.1);
            
            /* Typography */
            --font-heading: 'Montserrat', sans-serif;
            --font-body: 'Roboto', sans-serif;
            
            /* Dimensions */
            --radius-pill: 9999px;
            --radius-card: 4px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--ip-grey-bg);
            color: var(--text-main);
            font-family: var(--font-body);
            overflow-x: hidden;
            line-height: 1.6;
        }

        h1, h2, h3 { font-family: var(--font-heading); color: var(--ip-dark); font-weight: 700; }
        p { color: var(--text-muted); font-weight: 400; }

        /* --- LAYOUT --- */
        #canvas-container {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0; opacity: 1; pointer-events: none;
        }

        .content-wrapper {
            position: relative;
            z-index: 10;
        }

        /* --- HERO SECTION --- */
        .hero {
            min-height: 90vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            margin-bottom: 1.5rem;
            line-height: 1.1;
            color: var(--ip-blue);
            text-transform: uppercase;
            letter-spacing: -1px;
        }

        .hero p {
            font-size: 1.1rem;
            max-width: 600px;
            margin-bottom: 2.5rem;
            color: var(--text-main);
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }

        /* Reusing the Dashboard Button Style */
        .btn {
            font-family: var(--font-heading);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: var(--radius-pill);
            transition: all 0.3s ease;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background-color: var(--ip-blue);
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 101, 139, 0.3);
        }

        .btn-primary:hover {
            background-color: #004e6d;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 101, 139, 0.4);
        }

        .btn-secondary {
            background-color: var(--ip-card-bg);
            color: var(--ip-blue);
            border: 1px solid var(--ip-border);
        }

        .btn-secondary:hover {
            border-color: var(--ip-blue);
            background-color: #fff;
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        /* --- FEATURES (Card Style from Dashboard) --- */
        .features {
            padding: 80px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 3rem;
            color: var(--ip-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        /* Exact Dashboard Card Style */
        .feature-card {
            background: var(--ip-card-bg);
            border: 1px solid var(--ip-border);
            padding: 2rem;
            border-radius: var(--radius-card);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .feature-card:hover {
            border-color: var(--ip-blue);
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            background: var(--ip-grey-bg);
            width: 60px; height: 60px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            color: var(--ip-blue);
        }

        .feature-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            color: var(--ip-blue);
        }

        .feature-card p {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-muted);
        }

        /* --- STATS --- */
        .stats {
            background: var(--ip-card-bg);
            padding: 80px 20px;
            border-top: 1px solid var(--ip-border);
            border-bottom: 1px solid var(--ip-border);
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            text-align: center;
        }

        .stat-number {
            font-family: var(--font-heading);
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--ip-blue);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* --- FOOTER --- */
        .cta {
            text-align: center;
            padding: 100px 20px;
        }
        .cta h2 { margin-bottom: 1rem; }
        .cta p { margin-bottom: 2rem; }

        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--ip-border);
            background: #fff;
        }

        /* Animation Utils */
        .fade-in { opacity: 0; transform: translateY(20px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

    </style>
</head>
<body>
    
    <div id="canvas-container"></div>

    <div class="content-wrapper">
        
        <section class="hero fade-in">
            <span style="font-family: var(--font-heading); font-weight: 700; letter-spacing: 2px; color: var(--ip-blue); font-size: 0.9rem; margin-bottom: 1rem;">INTERACTIVE WORKSHOP SYSTEM</span>
            <h1>Live Situation Room</h1>
            <p>Collect, moderate, and display ideas instantly. <br>A professional tool for real-time collaborative workshops.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary">Start Workshop</a>
                <a href="login.php" class="btn btn-secondary">Admin Login</a>
            </div>
        </section>

        <section class="features">
            <h2 class="fade-in">System Capabilities</h2>
            <div class="features-grid fade-in">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>High Concurrency</h3>
                    <p>Optimized for large-scale workshops. Tested with 50+ simultaneous participants submitting ideas in real-time.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Real-Time Sync</h3>
                    <p>Submissions appear instantly on the dashboard. 2-second polling ensures the main screen is always up to date.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Focus Mode</h3>
                    <p>Admins can spotlight specific cards, dimming the rest of the interface to guide the room's attention.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>BYOD Ready</h3>
                    <p>Participants use their own devices via QR code. No app installation required—just scan and contribute.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure & Private</h3>
                    <p>Built for internal corporate use. Session isolation and secure login for moderators ensure data privacy.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Instant Visuals</h3>
                    <p>Data is automatically organized into a clean, masonry-style grid that adapts to any screen size.</p>
                </div>
            </div>
        </section>

        <section class="stats fade-in">
            <div class="stats-grid">
                <div class="stat">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat">
                    <div class="stat-number">2s</div>
                    <div class="stat-label">Latency</div>
                </div>
                <div class="stat">
                    <div class="stat-number">0</div>
                    <div class="stat-label">Setup Time</div>
                </div>
                <div class="stat">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Uptime</div>
                </div>
            </div>
        </section>

        <section class="cta fade-in">
            <h2>Ready to Deploy?</h2>
            <p>Set up your Live Situation Room in less than a minute.</p>
            <a href="register.php" class="btn btn-primary">Create Account</a>
        </section>

        <footer>
            &copy; 2026 Live Situation Room. Enterprise Workshop Solutions.
        </footer>
    </div>

    <script type="module">
        import * as THREE from 'three';
        
        // --- CONFIG FOR CLEAN CORPORATE LOOK ---
        const container = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        
        // FOG: Matches the CSS background (--ip-grey-bg: #f4f4f4)
        scene.fog = new THREE.FogExp2(0xf4f4f4, 0.030); 
        
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 100);
        camera.position.z = 10;
        
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        // Set background to transparent so CSS background shows, but Fog handles depth
        renderer.setClearColor(0xf4f4f4, 1); 
        container.appendChild(renderer.domElement);

        // --- PARTICLES (ADAPTED COLORS) ---
        const geometry = new THREE.BufferGeometry();
        const count = 400; // slightly denser
        const pos = new Float32Array(count * 3);
        const colors = new Float32Array(count * 3);
        
        const colorPrimary = new THREE.Color(0x00658b); // IP Blue
        const colorSecondary = new THREE.Color(0x32373c); // IP Dark
        
        for(let i = 0; i < count; i++) {
            pos[i * 3] = (Math.random() - 0.5) * 40; 
            pos[i * 3 + 1] = (Math.random() - 0.5) * 40; 
            pos[i * 3 + 2] = (Math.random() - 0.5) * 30; 
            
            // Randomly mix Blue and Dark Grey particles
            const mixedColor = Math.random() > 0.5 ? colorPrimary : colorSecondary;
            
            colors[i * 3] = mixedColor.r; 
            colors[i * 3 + 1] = mixedColor.g; 
            colors[i * 3 + 2] = mixedColor.b;
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        
        const material = new THREE.PointsMaterial({
            size: 0.12, // slightly larger dots for clean look
            vertexColors: true,
            transparent: true,
            opacity: 0.8
        });
        
        const particles = new THREE.Points(geometry, material);
        scene.add(particles);

        // --- FLOATING LABELS (Styled for Light Mode) ---
        const terms = ["VOTING", "IDEAS", "LIVE", "DATA", "SYNC", "TEAM", "CLOUD"];
        const labels = [];
        
        if (window.innerWidth > 800) {
            terms.forEach((term) => {
                const div = document.createElement('div');
                div.textContent = term;
                div.style.position = 'absolute';
                div.style.color = '#00658b'; // Blue text
                div.style.fontFamily = "'Montserrat', sans-serif";
                div.style.fontWeight = "700";
                div.style.fontSize = '10px';
                div.style.padding = '4px 10px';
                div.style.background = '#ffffff'; // White Card
                div.style.borderRadius = '20px'; // Pill shape
                div.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)'; // Soft shadow
                div.style.pointerEvents = 'none';
                container.appendChild(div);
                
                labels.push({
                    el: div,
                    pos: new THREE.Vector3((Math.random()-0.5)*20, (Math.random()-0.5)*10, (Math.random()-0.5)*10)
                });
            });
        }

        // --- ANIMATION ---
        let mouseX = 0, mouseY = 0;
        document.addEventListener('mousemove', (e) => {
            mouseX = (e.clientX - window.innerWidth/2) * 0.0005; // Reduced sensitivity
            mouseY = (e.clientY - window.innerHeight/2) * 0.0005;
        });

        const clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);
            const time = clock.getElapsedTime();

            particles.rotation.y = time * 0.03; // Slower rotation
            camera.rotation.x += (mouseY - camera.rotation.x) * 0.05;
            camera.rotation.y += (mouseX - camera.rotation.y) * 0.05;

            // Update Labels
            labels.forEach(l => {
                const v = l.pos.clone();
                v.applyMatrix4(particles.matrixWorld); 
                v.project(camera);
                
                if(Math.abs(v.z) > 1) {
                    l.el.style.opacity = 0;
                } else {
                    const x = (v.x * .5 + .5) * window.innerWidth;
                    const y = (v.y * -.5 + .5) * window.innerHeight;
                    l.el.style.transform = `translate(${x}px, ${y}px)`;
                    // Fade out as they go back into the fog
                    l.el.style.opacity = 1 - (v.z * 0.8); 
                }
            });

            renderer.render(scene, camera);
        }
        animate();

        // --- RESIZE ---
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        // --- FADE IN ---
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });
        
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
    </script>
</body>
</html>