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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@300;400;600&display=swap" rel="stylesheet">
    
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js"
            }
        }
    </script>

    <style>
        /* --- RESET & VARIABLES (From Reference) --- */
        :root {
            --bg-color: #050505; /* Deep Pure Black */
            --text-color: #f0f0f0;
            --highlight: #ffffff;
            --grid-line: rgba(255, 255, 255, 0.1);
            --font-head: 'Bebas Neue', display;
            --font-body: 'Manrope', sans-serif;
            --transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            color: var(--text-color);
            background: var(--bg-color);
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- TYPOGRAPHY --- */
        h1, h2, h3 {
            font-family: var(--font-head);
            text-transform: uppercase;
            font-weight: 400;
            letter-spacing: 1px;
            line-height: 0.9;
        }

        p {
            font-family: var(--font-body);
            font-weight: 300;
            color: #b0b0b0;
        }

        /* --- ANIMATIONS --- */
        .fade-in { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

        /* --- HERO SECTION --- */
        .hero {
            background: transparent; /* Changed from gradient to transparent for canvas */
            color: white;
            padding: 180px 20px 100px; /* More top padding */
            text-align: center;
            position: relative;
            min-height: 90vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }

        /* Canvas Container */
        #canvas-container {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1; opacity: 0.6; pointer-events: none;
        }

        .hero h1 {
            font-size: clamp(3.5rem, 12vw, 10rem);
            margin-bottom: 24px;
            color: var(--highlight);
            text-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 50px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            color: #ccc;
            letter-spacing: 0.5px;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            z-index: 20;
        }

        /* Redesigned Buttons */
        .btn {
            padding: 12px 32px;
            font-size: 1.2rem;
            font-family: var(--font-head);
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            border: 1px solid var(--highlight);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--highlight);
            backdrop-filter: blur(5px);
        }

        .btn-primary:hover {
            background: var(--highlight);
            color: var(--bg-color);
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(255,255,255,0.2);
        }

        .btn-secondary {
            background: transparent;
            color: #888;
            border-color: #444;
        }

        .btn-secondary:hover {
            border-color: var(--highlight);
            color: var(--highlight);
        }

        /* --- FEATURES SECTION (Grid Style) --- */
        .features {
            max-width: 1400px;
            margin: 0 auto;
            padding: 100px 20px;
            position: relative;
            z-index: 2;
        }

        .features h2 {
            font-size: clamp(2.5rem, 6vw, 5rem);
            text-align: center;
            margin-bottom: 80px;
            color: var(--highlight);
            border-left: none; /* Override ref style specifically for centered header */
        }

        /* The Grid Look */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1px; /* The grid line thickness */
            background: var(--grid-line);
            border: 1px solid var(--grid-line);
        }

        .feature-card {
            text-align: left;
            padding: 40px;
            background: var(--bg-color); /* Black cards */
            border-radius: 0; /* Square corners */
            transition: var(--transition);
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .feature-card:hover {
            background: #111;
            transform: none; /* Remove list translation */
            outline: 1px solid var(--highlight);
            z-index: 2;
            box-shadow: none;
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 20px;
            filter: grayscale(100%);
        }

        .feature-card h3 {
            font-family: var(--font-head);
            font-size: 2rem;
            margin-bottom: 12px;
            color: var(--highlight);
        }

        .feature-card p {
            color: #888;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* --- STATS SECTION (Mono-Box Style) --- */
        .stats {
            background: #0a0a0a;
            color: white;
            padding: 100px 20px;
            text-align: center;
            border-top: 1px solid var(--grid-line);
            border-bottom: 1px solid var(--grid-line);
            position: relative;
            z-index: 2;
        }

        .stats-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat {
            padding: 30px;
            border: 1px solid var(--grid-line);
            background: rgba(255,255,255,0.02);
            transition: var(--transition);
        }

        .stat:hover {
            border-color: var(--highlight);
            background: rgba(255,255,255,0.05);
        }

        .stat-number {
            font-family: var(--font-head);
            font-size: 5rem;
            font-weight: 400;
            margin-bottom: 8px;
            color: var(--highlight);
        }

        .stat-label {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #666;
            font-family: var(--font-body);
        }

        /* --- CTA SECTION --- */
        .cta {
            max-width: 1000px;
            margin: 100px auto;
            padding: 60px 40px;
            text-align: center;
            background: transparent;
            border: 1px solid var(--grid-line);
            border-radius: 0;
            position: relative;
            z-index: 2;
        }

        .cta:hover {
            border-color: #333;
        }

        .cta h2 {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--highlight);
        }

        .cta p {
            font-size: 1.2rem;
            color: #888;
            margin-bottom: 40px;
        }

        /* --- FOOTER --- */
        .footer {
            background: var(--bg-color);
            border-top: 1px solid var(--grid-line);
            color: #666;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .footer p {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #444;
        }

        @media (max-width: 768px) {
            .hero { padding-top: 120px; }
            .hero h1 { line-height: 0.85; }
            .features-grid { grid-template-columns: 1fr; }
            .feature-card { padding: 30px; }
        }
    </style>
</head>
<body>
    
    <div id="canvas-container"></div>

    <section class="hero fade-in">
        <h1>Live Situation<br>Room</h1>
        <p>Real-time collaborative workshops. Collect, moderate, and display ideas instantly with powerful visualization.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn btn-primary">Get Started Free</a>
            <a href="login.php" class="btn btn-secondary">Sign In</a>
        </div>
    </section>

    <section class="features">
        <h2 class="fade-in">System Capabilities</h2>
        <div class="features-grid fade-in">
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
                <p>Show/hide entries in real-time. Focus mode to spotlight important contributions. Full control over visibility.</p>
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

    <section class="stats">
        <div class="stats-grid fade-in">
            <div class="stat">
                <div class="stat-number">50+</div>
                <div class="stat-label">Participants</div>
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
                <div class="stat-label">Secure</div>
            </div>
        </div>
    </section>

    <section class="cta fade-in">
        <h2>Ready to Deploy?</h2>
        <p>Create your free account and start collecting ideas in minutes.</p>
        <a href="register.php" class="btn btn-primary">Create Account →</a>
    </section>

    <footer class="footer">
        <p>© 2026 Live Situation Room. Built for collaborative workshops.</p>
    </footer>

    <script type="module">
        import * as THREE from 'three';
        
        // --- CONFIG ---
        const container = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0x050505, 0.035);
        
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 100);
        camera.position.z = 10;
        
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.appendChild(renderer.domElement);

        // --- PARTICLES ---
        const geometry = new THREE.BufferGeometry();
        const count = 300;
        const pos = new Float32Array(count * 3);
        const colors = new Float32Array(count * 3);
        
        for(let i = 0; i < count; i++) {
            pos[i * 3] = (Math.random() - 0.5) * 40; 
            pos[i * 3 + 1] = (Math.random() - 0.5) * 40; 
            pos[i * 3 + 2] = (Math.random() - 0.5) * 30; 
            colors[i * 3] = 1.0; colors[i * 3 + 1] = 1.0; colors[i * 3 + 2] = 1.0;
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        
        const material = new THREE.PointsMaterial({
            size: 0.07,
            vertexColors: true,
            transparent: true,
            opacity: 0.6
        });
        
        const particles = new THREE.Points(geometry, material);
        scene.add(particles);

        // --- LABELS (Context Specific) ---
        const terms = ["VOTING", "IDEAS", "REAL-TIME", "DATA", "MODERATION", "WORKSHOP", "SYNC", "CLOUD"];
        const labels = [];
        
        if (window.innerWidth > 800) {
            terms.forEach((term) => {
                const div = document.createElement('div');
                div.textContent = term;
                div.style.position = 'absolute';
                div.style.color = '#fff';
                div.style.fontFamily = "'Manrope', sans-serif";
                div.style.fontSize = '10px';
                div.style.padding = '2px 6px';
                div.style.border = '1px solid rgba(255,255,255,0.3)';
                div.style.background = 'rgba(0,0,0,0.7)';
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
            mouseX = (e.clientX - window.innerWidth/2) * 0.001;
            mouseY = (e.clientY - window.innerHeight/2) * 0.001;
        });

        const clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);
            const time = clock.getElapsedTime();

            particles.rotation.y = time * 0.05;
            camera.rotation.x += (mouseY - camera.rotation.x) * 0.05;
            camera.rotation.y += (mouseX - camera.rotation.y) * 0.05;

            // Update Labels
            labels.forEach(l => {
                const v = l.pos.clone();
                v.applyMatrix4(particles.matrixWorld); // Rotate with particles
                v.project(camera);
                
                if(Math.abs(v.z) > 1) {
                    l.el.style.opacity = 0;
                } else {
                    const x = (v.x * .5 + .5) * window.innerWidth;
                    const y = (v.y * -.5 + .5) * window.innerHeight;
                    l.el.style.transform = `translate(${x}px, ${y}px)`;
                    l.el.style.opacity = 1 - (v.z * 0.5); // Fade if far
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