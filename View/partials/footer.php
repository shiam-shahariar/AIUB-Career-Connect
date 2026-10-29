        </main>

        <footer class="site-footer">
            <div class="container footer-grid">
                <div>
                    <h4>AIUB Career Connect</h4>
                    <p>Built for AIUB students, fresh graduates, alumni, and administrators to strengthen career connections.</p>
                </div>
                <div>
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php?page=home">Home</a></li>
                        <li><a href="index.php?page=opportunities">Opportunities</a></li>
                        <li><a href="index.php?page=alumni">Alumni Directory</a></li>
                        <li><a href="index.php?page=companies">Company Directory</a></li>
                        <?php if ($currentUser): ?>
                            <li><a href="index.php?page=notifications">Notifications</a></li>
                        <?php else: ?>
                            <li><a href="index.php?page=login">Sign in</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4>AIUB</h4>
                    <ul>
                        <li>
                            <address class="footer-address">
                                408/1 (Old KA 66/1), Kuratoli,<br>
                                Khilkhet, Dhaka 1229, Bangladesh
                            </address>
                        </li>
                        <li><a href="https://www.aiub.edu/" target="_blank" rel="noopener noreferrer">Visit the official AIUB website</a></li>
                    </ul>
                    <nav class="footer-social-links" aria-label="AIUB social media">
                        <a class="footer-social-link" href="https://facebook.com/aiub.edu" target="_blank" rel="noopener noreferrer" aria-label="AIUB on Facebook">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.4 21v-8.2h2.8l.4-3.2h-3.2V7.5c0-.9.3-1.6 1.6-1.6h1.7V3.1c-.3 0-1.4-.1-2.6-.1-2.6 0-4.4 1.6-4.4 4.5v2.1H7v3.2h2.7V21z"/></svg>
                        </a>
                        <a class="footer-social-link" href="https://www.instagram.com/aiubedu/" target="_blank" rel="noopener noreferrer" aria-label="AIUB on Instagram">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle class="footer-social-icon__dot" cx="17.7" cy="6.8" r="1"/></svg>
                        </a>
                        <a class="footer-social-link" href="https://www.youtube.com/channel/UCpwnTakRAAks9hpPr_ySxog" target="_blank" rel="noopener noreferrer" aria-label="AIUB on YouTube">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 7.2a2.8 2.8 0 0 0-2-2C17.3 4.7 12 4.7 12 4.7s-5.3 0-7 .5a2.8 2.8 0 0 0-2 2A29 29 0 0 0 2.5 12a29 29 0 0 0 .5 4.8 2.8 2.8 0 0 0 2 2c1.7.5 7 .5 7 .5s5.3 0 7-.5a2.8 2.8 0 0 0 2-2 29 29 0 0 0 .5-4.8 29 29 0 0 0-.5-4.8Z"/><path class="footer-social-icon__play" d="m10 15.5 5-3.5-5-3.5z"/></svg>
                        </a>
                        <a class="footer-social-link" href="https://www.linkedin.com/school/aiubedu/" target="_blank" rel="noopener noreferrer" aria-label="AIUB on LinkedIn">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.2 8.5H2.4V21h2.8zM3.8 3a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4ZM21.6 13.8c0-3.8-2-5.6-4.7-5.6a4 4 0 0 0-3.6 2v-1.7h-2.8V21h2.8v-7c0-1.8.4-3.5 2.6-3.5s2.3 2 2.3 3.6V21H21z"/></svg>
                        </a>
                    </nav>
                </div>
            </div>

            <div class="container footer-meta">
                <div class="footer-meta__content">
                    <span>© <?php echo date('Y'); ?> American International University-Bangladesh</span>
                    <span class="footer-credit">Powered by MD SHAHARIAR SHIAM</span>
                </div>
            </div>
        </footer>
    </div>

    <script src="Javascript/Script.js?v=<?php echo (int) filemtime(__DIR__ . '/../../Javascript/Script.js'); ?>"></script>
</body>
</html>
