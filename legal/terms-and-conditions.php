<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - Alvion Hospital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.8.1/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .section-icon {
            width: 24px;
            height: 24px;
            color: #0d9488;
        }
        .number-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: #0d9488;
            color: white;
            border-radius: 50%;
            font-weight: 600;
            font-size: 14px;
            margin-right: 12px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="max-w-screen-xl flex items-center justify-between mx-auto px-4 py-3">
            <a href="../landing.php" class="flex items-center flex-shrink-0">
                <img src="../assets/img/alvion-logo-removebg.png" alt="Alvion">
            </a>
            <a href="../landing.php" class="text-gray-700 hover:text-teal-custom transition-colors flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                <span>Back to Home</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <div class="flex items-center gap-4 mb-4">
                <div class="p-3 bg-teal-100 rounded-lg">
                    <i data-lucide="file-check" class="w-8 h-8 text-teal-custom"></i>
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Terms and Conditions</h1>
                    <p class="text-gray-600 mt-1">Alvion Hospital</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500 pt-4 border-t">
                <i data-lucide="calendar" class="w-4 h-4"></i>
                <span>Effective Date: <?php echo date('F d, Y'); ?></span>
            </div>
        </div>

        <!-- Introduction -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <p class="text-gray-700 leading-relaxed">
                These Terms and Conditions outline the rules for using the Services provided by Alvion Hospital, a healthcare institution operating in the Philippines.
            </p>
        </div>

        <!-- Section 1: User Responsibilities -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">1</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="user-check" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">User Responsibilities</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        You agree to:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Provide accurate and complete information.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Keep login credentials secure.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Use the Services only for lawful, personal purposes.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 2: Appointment and Service Requests -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">2</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="calendar-check" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Appointment and Service Requests</h2>
                    </div>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Appointment confirmations are subject to doctor availability.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Emergency medical cases must be handled onsite or through emergency channels.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Alvion reserves the right to reschedule or cancel appointments when necessary.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 3: Patient Accounts -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">3</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="user-circle" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Patient Accounts</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        If you create an online patient account:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="shield" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">You are responsible for all activity under your account.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="bell" class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">You must notify us immediately of unauthorized access.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 4: Payments and Billing -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">4</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="credit-card" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Payments and Billing</h2>
                    </div>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="file-text" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Fees for hospital services are communicated through invoices or billing advisories.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="lock" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Online payments (if available) must be made through approved channels.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="refresh-ccw" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Refunds follow Alvion's internal policies and Philippine laws.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 5: Electronic Communications -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">5</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="mail" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Electronic Communications</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        By using the Services, you allow Alvion to send you:
                    </p>
                    <ul class="space-y-3 ml-8 mb-4">
                        <li class="flex items-start gap-3">
                            <i data-lucide="calendar" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Appointment reminders</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="heart-pulse" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Medical updates</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="receipt" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Billing notices</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="megaphone" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">System announcements</span>
                        </li>
                    </ul>
                    <div class="p-4 bg-teal-50 border-l-4 border-teal-400 rounded">
                        <p class="text-gray-700">
                            <i data-lucide="info" class="w-4 h-4 inline mr-2 text-teal-custom"></i>
                            You may opt out of non-essential communications.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6: Service Availability -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">6</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="server" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Service Availability</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        Alvion may:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="refresh-cw" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Update, suspend, or discontinue portions of the Services.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="wrench" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Perform system maintenance without prior notice.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 7: Indemnification -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">7</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="shield-alert" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Indemnification</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        You agree to indemnify and hold harmless Alvion from any claims arising from your misuse of the Services or violation of these Terms.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 8: Termination of Use -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">8</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="ban" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Termination of Use</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        We may suspend or terminate access for:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Violations of the Terms</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Fraudulent activity</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Abuse of services</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 9: Governing Law and Venue -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">9</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="gavel" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Governing Law and Venue</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        These Terms and Conditions are governed by Philippine law. Any disputes shall be brought before the proper courts of Taguig City, Philippines.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-gray-600 text-sm">
                    Last updated: <?php echo date('F d, Y'); ?>
                </p>
                <a href="../landing.php" class="inline-flex items-center gap-2 px-6 py-3 bg-teal-custom text-white rounded-lg hover:bg-teal-700 transition-colors">
                    <i data-lucide="home" class="w-5 h-5"></i>
                    <span>Return to Home</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 py-8 mt-12">
        <div class="max-w-screen-xl mx-auto px-4 text-center">
            <p class="text-gray-400 text-sm">
                &copy; <?php echo date('Y'); ?> Alvion Hospital. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.8.1/flowbite.min.js"></script>
    <script>
        // Initialize Lucide icons
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
        
        // Navbar scroll effect
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>

