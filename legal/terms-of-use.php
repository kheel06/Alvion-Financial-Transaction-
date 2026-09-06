<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Use - Alvion Hospital</title>
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
                    <i data-lucide="file-text" class="w-8 h-8 text-teal-custom"></i>
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Terms of Use</h1>
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
                Welcome to Alvion Hospital ("Alvion", "we", "our", "us"). These Terms of Use govern your access to and use of our website, online services, patient portals, and other digital platforms (collectively, the "Services"). By accessing or using our Services, you agree to be bound by these Terms.
            </p>
        </div>

        <!-- Section 1: Acceptance of Terms -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">1</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="check-circle" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Acceptance of Terms</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        By using our Services, you confirm that you:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Are at least 18 years old or have parental/guardian consent.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Agree to comply with these Terms and all applicable Philippine laws.</span>
                        </li>
                    </ul>
                    <div class="mt-4 p-4 bg-amber-50 border-l-4 border-amber-400 rounded">
                        <p class="text-gray-700">
                            <strong>Important:</strong> If you do not agree, you must stop using the Services.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Medical Disclaimer -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">2</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="alert-triangle" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Medical Disclaimer</h2>
                    </div>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Information provided through the Services is for general informational purposes only.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">It does not replace professional medical diagnosis or treatment.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Always consult a licensed healthcare provider for medical concerns.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 3: Use of the Services -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">3</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="shield" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Use of the Services</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        You agree not to:
                    </p>
                    <ul class="space-y-3 ml-8 mb-4">
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Use the Services for unlawful purposes.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Attempt to breach or bypass security measures.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Upload harmful or malicious content.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Disrupt or interfere with service operations.</span>
                        </li>
                    </ul>
                    <div class="p-4 bg-red-50 border-l-4 border-red-400 rounded">
                        <p class="text-gray-700">
                            <strong>Note:</strong> We reserve the right to suspend or terminate access for violations.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Intellectual Property -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">4</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="copyright" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Intellectual Property</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        All content, trademarks, logos, and materials on the Services are the property of Alvion Hospital or its licensors and are protected by Philippine intellectual property laws. You may not reproduce, modify, distribute, or use them without written permission.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 5: Third-Party Links -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">5</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="link" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Third-Party Links</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        Our Services may contain links to third-party websites. We are not responsible for their content, privacy practices, or operations.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 6: Limitation of Liability -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">6</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="scale" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Limitation of Liability</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        To the fullest extent permitted by law, Alvion shall not be liable for:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Any damages resulting from your use or inability to use the Services.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Errors, interruptions, viruses, or system failures.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Unauthorized access to your account or information.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 7: Changes to the Terms -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">7</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="refresh-cw" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Changes to the Terms</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        Alvion may update these Terms at any time. Continued use of the Services constitutes acceptance of the revised Terms.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 8: Governing Law -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">8</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="gavel" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Governing Law</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        These Terms are governed by the laws of the Republic of the Philippines.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 9: Contact Information -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">9</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="mail" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Contact Information</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        For questions, contact us at:
                    </p>
                    <div class="bg-teal-50 rounded-lg p-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="building" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="font-semibold text-gray-900">Alvion Hospital – Legal & Compliance Department</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i data-lucide="mail" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="text-gray-700">Email: <a href="mailto:legal@alvionhealth.ph" class="text-teal-custom hover:underline">legal@alvionhealth.ph</a></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i data-lucide="phone" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="text-gray-700">Phone: <a href="tel:+63288882586" class="text-teal-custom hover:underline">(02) 8888-ALVN (2586)</a></p>
                            </div>
                        </div>
                    </div>
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

