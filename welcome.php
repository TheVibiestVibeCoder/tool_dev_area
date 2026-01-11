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
    <title>Live Situation Room - System Active</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@300;400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js"
            }
        }
    </script>

    <style>
        /* --- CORE SETTINGS --- */
        :root {
            --bg-deep: #030303;
            --bg-card: rgba(10, 10, 10, 0.6);
            --text-main: #e0e0e0;
            --text-muted: #666666;
            --highlight: #ffffff;
            --accent-safe: #33ff00; /* Tiny status indicators */
            --grid-line: rgba(255, 255, 255, 0.08);
            
            --font-display: 'Bebas Neue', display;
            --font-body: 'Manrope', sans-serif;
            --font-tech: 'JetBrains Mono', monospace;
            
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            color: var(--text-main);
            background-color: var(--bg-deep);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- ATMOSPHERE LAYERS --- */
        /* 1. Cinematic Noise Overlay */
        .noise-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 90;
        }

        /* 2. Vignette */
        .vignette {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.8) 100%);
            pointer-events: none; z-index: 80;
        }

        /* 3. ThreeJS Canvas */
        #canvas-container {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1; opacity: 0.8;
        }

        /* --- TYPOGRAPHY --- */
        h1, h2, h3 {
            font-family: var(--font-display);
            text-transform: uppercase;
            font-weight: 400;
            letter-spacing: 2px;
            line-height: 0.9;
            color: var(--highlight);
        }

        .tech-label {
            font-family: var(--font-tech);
            font-size: 0.75rem;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 8px;
        }

        p {
            font-weight: 300;
            color: #999;
            line-height: 1.7;
        }

        /* --- HERO SECTION --- */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
            z-index: 10;
        }

        .hero-content {
            max-width: 900px;
            position: relative;
        }

        .hero h1 {
            font-size: clamp(4rem, 15vw, 11rem);
            margin-bottom: 30px;
            text-shadow: 0 0 30px rgba(255,255,255,0.1);
        }

        .hero p {
            font-size: 1.25rem;
            max-width: 600px;
            margin: 0 auto 50px;
            color: #ccc;
        }

        /* --- BUTTONS --- */
        .btn-group {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            position: relative;
            padding: 16px 40px;
            font-family: var(--font-display);
            font-size: 1.3rem;
            text-decoration: none;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: all 0.4s var(--ease-out);
            border: 1px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }

        .btn-primary {
            background: rgba(255,255,255,0.05);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-primary:hover {
            background: white;
            color: black;
            box-shadow: 0 0 40px rgba(255,255,255,0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #888;
            border-color: #333;
        }

        .btn-secondary:hover {
            color: white;
            border-color: white;
        }

        /* --- GRID SYSTEM UI --- */
        .section-header {
            text-align: center;
            padding: 120px 20px 60px;
            position: relative;
            z-index: 10;
        }
        
        .section-header h2 { font-size: clamp(2rem, 5vw, 4rem); }

        .features {
            position: relative;
            z-index: 10;
            max-width: 1600px;
            margin: 0 auto;
            border-top: 1px solid var(--grid-line);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1px;
            background: var(--grid-line);
            border-bottom: 1px solid var(--grid-line);
        }

        .feature-card {
            background: var(--bg-deep);
            padding: 50px 40px;
            position: relative;
            transition: background 0.3s ease;
        }

        .feature-card:hover {
            background: #080808;
        }

        /* Technical Corners on Cards */
        .feature-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 10px; height: 10px;
            border-top: 1px solid rgba(255,255,255,0.5);
            border-right: 1px solid rgba(255,255,255,0.5);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .feature-card:hover::after { opacity: 1; }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 25px;
            opacity: 0.7;
        }

        .feature-card h3 {
            font-size: 2rem;
            margin-bottom: 15px;
        }

        /* --- STATS BAR --- */
        .stats-bar {
            position: relative;
            z-index: 10;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            border-bottom: 1px solid var(--grid-line);
            background: #050505;
        }

        .stat-item {
            padding: 40px;
            text-align: center;
            border-right: 1px solid var(--grid-line);
            position: relative;
        }

        .stat-val {
            font-family: var(--font-display);
            font-size: 4rem;
            display: block;
            margin-bottom: 5px;
        }
        
        /* Status Dot */
        .status-dot {
            display: inline-block;
            width: 6px; height: 6px;
            background: var(--accent-safe);
            border-radius: 50%;
            margin-right: 8px;
            box-shadow: 0 0 5px var(--accent-safe);
            animation: pulse 2s infinite;
        }

        @keyframes pulse { 0% {opacity:1;} 50% {opacity:0.3;} 100% {opacity:1;} }

        /* --- CTA & FOOTER --- */
        .cta-section {
            padding: 150px 20px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        footer {
            border-top: 1px solid var(--grid-line);
            padding: 40px;
            text-align: center;
            color: #444;
            position: relative;
            z-index: 10;
            font-family: var(--font-tech);
            font-size: 0.8rem;
            background: #020202;
        }

        /* --- ANIMATION UTILS --- */
        .fade-up { opacity: 0; transform: translateY(40px); transition: all 1s var(--ease-out); }
        .fade-up.visible { opacity: 1; transform: translateY(0); }

        @media (max-width: 768px) {
            .hero h1 { line-height: 0.8; }
            .grid-container { grid-template-columns: 1fr; }
            .stat-item { border-right: none; border-bottom: 1px solid var(--grid-line); }
        }
    </style>
</head>
<body>

    <div class="noise-overlay"></div>
    <div class="vignette"></div>
    <div id="canvas-container"></div>

    <section class="hero">
        <div class="hero-content fade-up">
            <span class="tech-label">/// SYSTEM_READY // V.2.0.4</span>
            <h1>Situation<br>Room</h1>
            <p>High-fidelity collaborative environment. Visualize data, moderate inputs, and display intelligence in real-time.</p>
            
            <div class="btn-group">
                <a href="register.php" class="btn btn-primary">Initialize System</a>
                <a href="login.php" class="btn btn-secondary">Admin Login</a>
            </div>
        </div>
    </section>

    <div class="stats-bar fade-up">
        <div class="stat-item">
            <span class="tech-label"><span class="status-dot"></span>LATENCY</span>
            <span class="stat-val">2ms</span>
        </div>
        <div class="stat-item">
            <span class="tech-label">CAPACITY</span>
            <span class="stat-val">50+</span>
        </div>
        <div class="stat-item">
            <span class="tech-label">UPTIME</span>
            <span class="stat-val">99.9%</span>
        </div>
        <div class="stat-item">
            <span class="tech-label">SECURITY</span>
            <span class="stat-val">AES</span>
        </div>
    </div>

    <section class="section-header fade-up">
        <span class="tech-label">/// MODULES</span>
        <h2>System Capabilities</h2>
    </section>

    <section class="features fade-up">
        <div class="grid-container">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Real-Time Sync</h3>
                <p>Instantaneous data propagation across all connected clients. No refresh required.</p>
                <span class="tech-label" style="margin-top:20px;">[SOCKET_OPEN]</span>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Admin Control</h3>
                <p>Granular moderation tools. Hide, focus, or delete entries with a single tactical click.</p>
                <span class="tech-label" style="margin-top:20px;">[AUTH_REQ]</span>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Data Viz</h3>
                <p>Built-in projectors for word clouds, voting grids, and prioritized lists.</p>
                <span class="tech-label" style="margin-top:20px;">[RENDER_ON]</span>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile Input</h3>
                <p>Participants interface via QR codes. Optimized for rapid data entry on the move.</p>
                <span class="tech-label" style="margin-top:20px;">[RESPONSIVE]</span>
            </div>
        </div>
    </section>

    <section class="cta-section fade-up">
        <span class="tech-label">/// DEPLOYMENT</span>
        <h2>Ready for Operations?</h2>
        <p style="margin-bottom: 30px;">Secure your instance now.</p>
        <a href="register.php" class="btn btn-primary">Create Account</a>
    </section>

    <footer>
        <p>LIVE SITUATION ROOM © 2026 // SYSTEM ID: A-994 // VIENNA NODE</p>
    </footer>

    <script type="module">
        import * as THREE from 'three';

        // --- SCENE SETUP ---
        const container = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        // Deep fog to hide the edges
        scene.fog = new THREE.FogExp2(0x030303, 0.04);

        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.z = 12;

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.appendChild(renderer.domElement);

        // --- PARTICLES (THE DATA CLOUD) ---
        const geometry = new THREE.BufferGeometry();
        const count = 600; // Denser cloud
        const posArray = new Float32Array(count * 3);
        const randomArray = new Float32Array(count); // For twinkling

        for(let i = 0; i < count * 3; i+=3) {
            // Spread particles wide
            posArray[i] = (Math.random() - 0.5) * 50; 
            posArray[i+1] = (Math.random() - 0.5) * 40; 
            posArray[i+2] = (Math.random() - 0.5) * 30;
            
            randomArray[i/3] = Math.random();
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
        geometry.setAttribute('aRandom', new THREE.BufferAttribute(randomArray, 1));

        const material = new THREE.PointsMaterial({
            size: 0.08,
            color: 0xffffff,
            transparent: true,
            opacity: 0.4,
            blending: THREE.AdditiveBlending
        });

        const particlesMesh = new THREE.Points(geometry, material);
        scene.add(particlesMesh);

        // --- FLOATING DATA LABELS ---
        const terms = ["SYNC", "DATA", "NODE", "GRID", "LIVE", "CORE", "AUTH", "NET"];
        const labels = [];

        // Create DOM elements for labels (Hybrid approach: HTML overlaying Canvas)
        if (window.innerWidth > 768) {
            terms.forEach((term, i) => {
                const el = document.createElement('div');
                el.textContent = term;
                el.style.position = 'absolute';
                el.style.fontFamily = "'JetBrains Mono', monospace";
                el.style.fontSize = '10px';
                el.style.color = 'rgba(255,255,255,0.4)';
                el.style.border = '1px solid rgba(255,255,255,0.1)';
                el.style.padding = '2px 4px';
                el.style.pointerEvents = 'none';
                el.style.whiteSpace = 'nowrap';
                container.appendChild(el);

                // Assign random 3D position
                labels.push({
                    element: el,
                    position: new THREE.Vector3(
                        (Math.random() - 0.5) * 30,
                        (Math.random() - 0.5) * 20,
                        (Math.random() - 0.5) * 10
                    ),
                    offset: Math.random() * 100
                });
            });
        }

        // --- ANIMATION LOOP ---
        let mouseX = 0;
        let mouseY = 0;
        let targetX = 0;
        let targetY = 0;

        const windowHalfX = window.innerWidth / 2;
        const windowHalfY = window.innerHeight / 2;

        document.addEventListener('mousemove', (event) => {
            mouseX = (event.clientX - windowHalfX);
            mouseY = (event.clientY - windowHalfY);
        });

        const clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);
            const elapsedTime = clock.getElapsedTime();

            targetX = mouseX * 0.001;
            targetY = mouseY * 0.001;

            // Smooth camera movement
            particlesMesh.rotation.y += 0.001;
            particlesMesh.rotation.x += 0.0005;

            // Mouse parallax
            particlesMesh.rotation.y += 0.05 * (targetX - particlesMesh.rotation.y);
            particlesMesh.rotation.x += 0.05 * (targetY - particlesMesh.rotation.x);

            // Update Labels
            labels.forEach(label => {
                // Gentle floating
                const tempV = label.position.clone();
                tempV.y += Math.sin(elapsedTime + label.offset) * 0.5;
                
                // Project 3D point to 2D screen
                tempV.project(camera);

                // Convert to pixels
                const x = (tempV.x * .5 + .5) * window.innerWidth;
                const y = (tempV.y * -.5 + .5) * window.innerHeight;

                // Only show if in front of camera
                if (tempV.z < 1 && Math.abs(tempV.x) < 1.2 && Math.abs(tempV.y) < 1.2) {
                    label.element.style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px)`;
                    label.element.style.opacity = 1 - Math.abs(tempV.z); // Fade if far away
                } else {
                    label.element.style.opacity = 0;
                }
            });

            renderer.render(scene, camera);
        }

        animate();

        // --- RESIZE HANDLER ---
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        // --- INTERSECTION OBSERVER FOR FADE UPS ---
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    </script>
</body>
</html>