/**
 * DYNAMIC LOGIN BACKGROUND SYSTEM
 * Professional Enterprise Grade Slideshow & Video Engine
 */
window.DynamicLoginBackground = (function() {
    'use strict';

    const config = {
        assetsPath: 'assets/login-bg/',
        images: ['bg1.jpg', 'bg2.jpg', 'bg3.jpg', 'bg4.jpg'],
        video: 'background-video.mp4',
        interval: 8000,
        fadeDuration: 1500
    };

    let currentIndex = 0;
    let container;

    function init() {
        container = document.getElementById('dynamic-bg-container');
        if (!container) return;

        const videoElement = container.querySelector('video');
        if (videoElement) {
            handleVideoMode(videoElement);
        } else {
            handleSlideshowMode();
        }
    }

    function handleVideoMode(video) {
        video.play().catch(e => {
            handleSlideshowMode();
        });
    }

    function handleSlideshowMode() {
        if (config.images.length === 0) return;
        renderImage(config.images[currentIndex]);
        setInterval(() => {
            currentIndex = (currentIndex + 1) % config.images.length;
            switchImage(config.images[currentIndex]);
        }, config.interval);
    }

    function renderImage(filename) {
        const layer = document.createElement('div');
        layer.className = 'bg-layer active';
        layer.style.backgroundImage = `url('${config.assetsPath}${filename}')`;
        container.appendChild(layer);
    }

    function switchImage(filename) {
        const newLayer = document.createElement('div');
        newLayer.className = 'bg-layer next';
        newLayer.style.backgroundImage = `url('${config.assetsPath}${filename}')`;
        newLayer.style.opacity = '0';
        container.appendChild(newLayer);

        // Force reflow
        newLayer.offsetHeight;

        newLayer.style.transition = `opacity ${config.fadeDuration}ms ease-in-out`;
        newLayer.style.opacity = '1';

        setTimeout(() => {
            const oldLayers = container.querySelectorAll('.bg-layer.active');
            oldLayers.forEach(l => l.remove());
            newLayer.className = 'bg-layer active';
        }, config.fadeDuration);
    }

    return {
        init: init
    };
})();

document.addEventListener('DOMContentLoaded', () => window.DynamicLoginBackground.init());
