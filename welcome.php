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
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js"
            }
        }
    </script>

    <style>
        /* --- DESIGN SYSTEM (Monochrome / Bebas) --- */
        :root {
            /* Neutrals */
            --bg-body: #f5f5f5;
            --bg-card: #ffffff;
            --text-main: #111111;
            --text-muted: #666666;
            --border-color: #e0e0e0;
            
            /* Accents */
            --color-green: #27ae60; 
            
            /* Typography */
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'Inter', sans-serif;
            
            /* UI */
            --radius-btn: 4px;
            --radius-card: 4px;
            --shadow: 0 4px 6px rgba(0,0,0,0.03);
            --shadow-hover: 0 12px 24px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-body);
            overflow-x: hidden;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3 { font-family: var(--font-head); color: var(--text-main); font-weight: 400; line-height: 1; }
        p { color: var(--text-muted); font-weight: 400; font-size: 1.1rem; }

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

        .hero-label {
            font-family: var(--font-body);
            font-weight: 600;
            letter-spacing: 2px;
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 50px;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(5px);
        }

        .hero h1 {
            font-size: clamp(4rem, 10vw, 8rem);
            margin-bottom: 1.5rem;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero p {
            max-width: 600px;
            margin-bottom: 3rem;
            color: var(--text-main);
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }

        /* --- BUTTONS --- */
        .btn {
            font-family: var(--font-head);
            font-size: 1.2rem;
            letter-spacing: 1px;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: var(--radius-btn);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: var(--text-main);
            color: #fff;
            border-color: var(--text-main);
        }

        .btn-primary:hover {
            background-color: #333;
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background-color: rgba(255,255,255,0.8);
            color: var(--text-main);
            border-color: var(--text-main);
            backdrop-filter: blur(5px);
        }

        .btn-secondary:hover {
            background-color: var(--text-main);
            color: #fff;
            transform: translateY(-2px);
        }

        /* --- FEATURES --- */
        .features {
            padding: 100px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 4rem;
            text-transform: uppercase;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .feature-card:hover {
            border-color: var(--text-main);
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .feature-icon {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            background: var(--bg-body);
            width: 60px; height: 60px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            letter-spacing: 0.5px;
        }

        .feature-card p {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-muted);
        }

        /* --- STATS --- */
        .stats {
            background: var(--text-main);
            padding: 80px 20px;
            color: #fff;
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
            font-family: var(--font-head);
            font-size: 5rem;
            color: #fff;
            margin-bottom: 0;
            line-height: 1;
        }

        .stat-label {
            font-family: var(--font-body);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #888;
            font-weight: 600;
            margin-top: 10px;
        }

        /* --- CTA --- */
        .cta {
            text-align: center;
            padding: 120px 20px;
            background: var(--bg-body);
        }
        .cta h2 { font-size: 4rem; margin-bottom: 1rem; }
        .cta p { margin-bottom: 3rem; }

        /* --- FOOTER --- */
        footer {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
            background: #fff;
            font-family: var(--font-body);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Animation Utils */
        .fade-in { opacity: 0; transform: translateY(20px); transition: opacity 0.8s ease, transform 0.8s ease; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

        @media (max-width: 768px) {
            .hero h1 { font-size: 3.5rem; }
            .features h2 { font-size: 2.5rem; }
            .cta h2 { font-size: 3rem; }
            .hero-buttons { flex-direction: column; width: 100%; max-width: 300px; }
            .btn { width: 100%; }
        }

    </style>
</head>
<body>
    
    <div id="canvas-container"></div>

    <div class="content-wrapper">
        
        <section class="hero fade-in">
            <div class="hero-label">Enterprise Workshop System</div>
            <h1>Live Situation Room</h1>
            <p>Real-time collaboration. Instant visual feedback. Zero friction.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary">Get Started</a>
                <a href="login.php" class="btn btn-secondary">Admin Login</a>
            </div>
        </section>

        <section class="features">
            <h2 class="fade-in">System Capabilities</h2>
            <div class="features-grid fade-in">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-users-rays"></i></div>
                    <h3>High Concurrency</h3>
                    <p>Optimized for large-scale workshops. Handle 50+ simultaneous participants submitting ideas in real-time without lag.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-bolt-lightning"></i></div>
                    <h3>Real-Time Sync</h3>
                    <p>Submissions appear instantly on the dashboard. Live polling ensures the main screen is always up to date.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-bullseye"></i></div>
                    <h3>Focus Mode</h3>
                    <p>Admins can spotlight specific cards, dimming the rest of the interface to guide the room's attention instantly.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
                    <h3>BYOD Ready</h3>
                    <p>Participants use their own devices via QR code. No app installation required—just scan and contribute.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <h3>Secure & Private</h3>
                    <p>Built for internal corporate use. Session isolation and secure login for moderators ensure data privacy.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <h3>Instant Visuals</h3>
                    <p>Data is automatically organized into a clean, masonry-style grid that adapts perfectly to any screen size.</p>
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
                    <div class="stat-number">0s</div>
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
        
        // --- CONFIG FOR CLEAN MONOCHROME LOOK ---
        const container = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        
        // FOG: Matches the CSS background (#f5f5f5)
        scene.fog = new THREE.FogExp2(0xf5f5f5, 0.035); 
        
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 100);
        camera.position.z = 10;
        
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setClearColor(0xf5f5f5, 1); 
        container.appendChild(renderer.domElement);

        // --- PARTICLES (BLACK & GREY) ---
        const geometry = new THREE.BufferGeometry();
        const count = 450; 
        const pos = new Float32Array(count * 3);
        const colors = new Float32Array(count * 3);
        
        const colorPrimary = new THREE.Color(0x111111); // Black
        const colorSecondary = new THREE.Color(0xaaaaaa); // Grey
        
        for(let i = 0; i < count; i++) {
            pos[i * 3] = (Math.random() - 0.5) * 40; 
            pos[i * 3 + 1] = (Math.random() - 0.5) * 40; 
            pos[i * 3 + 2] = (Math.random() - 0.5) * 30; 
            
            // Randomly mix Black and Grey
            const mixedColor = Math.random() > 0.6 ? colorPrimary : colorSecondary;
            
            colors[i * 3] = mixedColor.r; 
            colors[i * 3 + 1] = mixedColor.g; 
            colors[i * 3 + 2] = mixedColor.b;
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        
        const material = new THREE.PointsMaterial({
            size: 0.12, 
            vertexColors: true,
            transparent: true,
            opacity: 0.6
        });
        
        const particles = new THREE.Points(geometry, material);
        scene.add(particles);

        // --- FLOATING LABELS (Monochrome) ---
        const terms = ["VOTING", "IDEAS", "LIVE", "DATA", "SYNC", "TEAM", "CLOUD"];
        const labels = [];
        
        if (window.innerWidth > 800) {
            terms.forEach((term) => {
                const div = document.createElement('div');
                div.textContent = term;
                div.style.position = 'absolute';
                div.style.color = '#111111'; 
                div.style.fontFamily = "'Bebas Neue', sans-serif";
                div.style.fontSize = '14px';
                div.style.letterSpacing = '1px';
                div.style.padding = '4px 12px';
                div.style.background = '#ffffff'; 
                div.style.border = '1px solid #e0e0e0';
                div.style.borderRadius = '4px'; 
                div.style.boxShadow = '0 2px 10px rgba(0,0,0,0.05)'; 
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
            mouseX = (e.clientX - window.innerWidth/2) * 0.0005; 
            mouseY = (e.clientY - window.innerHeight/2) * 0.0005;
        });

        const clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);
            const time = clock.getElapsedTime();

            particles.rotation.y = time * 0.03; 
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