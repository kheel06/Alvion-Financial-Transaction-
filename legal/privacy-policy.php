<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Alvion Hospital</title>
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
                    <i data-lucide="shield-check" class="w-8 h-8 text-teal-custom"></i>
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Privacy Policy</h1>
                    <p class="text-gray-600 mt-1">Alvion Hospital</p>
                </div>
            </div>
            <div class="space-y-2 pt-4 border-t">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                    <span>Effective Date: <?php echo date('F d, Y'); ?></span>
                </div>
                <div class="flex items-center gap-2 text-sm text-teal-custom">
                    <i data-lucide="file-check" class="w-4 h-4"></i>
                    <span>Compliant with the Philippine Data Privacy Act of 2012 – RA 10173</span>
                </div>
            </div>
        </div>

        <!-- Introduction -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <p class="text-gray-700 leading-relaxed">
                Alvion Hospital ("Alvion", "we") values your privacy and is committed to protecting your personal and medical information in accordance with the Data Privacy Act of 2012 (RA 10173), its IRR, and all related issuances of the National Privacy Commission (NPC).
            </p>
        </div>

        <!-- Section 1: Information We Collect -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">1</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="database" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Information We Collect</h2>
                    </div>
                    
                    <!-- A. Personal Information -->
                    <div class="ml-8 mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i data-lucide="user" class="w-5 h-5 text-teal-custom"></i>
                            A. Personal Information
                        </h3>
                        <ul class="space-y-2 ml-6">
                            <li class="flex items-start gap-3">
                                <i data-lucide="check" class="w-4 h-4 text-teal-custom mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Full name</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="check" class="w-4 h-4 text-teal-custom mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Address</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="check" class="w-4 h-4 text-teal-custom mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Contact details</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="check" class="w-4 h-4 text-teal-custom mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Date of birth</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="check" class="w-4 h-4 text-teal-custom mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Government-issued IDs</span>
                            </li>
                        </ul>
                    </div>

                    <!-- B. Sensitive Personal Information -->
                    <div class="ml-8 mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i data-lucide="lock" class="w-5 h-5 text-red-500"></i>
                            B. Sensitive Personal Information
                        </h3>
                        <ul class="space-y-2 ml-6">
                            <li class="flex items-start gap-3">
                                <i data-lucide="file-text" class="w-4 h-4 text-red-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Medical history and records</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="file-text" class="w-4 h-4 text-red-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Laboratory and diagnostic results</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="file-text" class="w-4 h-4 text-red-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Hospitalization and treatment information</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="file-text" class="w-4 h-4 text-red-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">PhilHealth/insurance details</span>
                            </li>
                        </ul>
                    </div>

                    <!-- C. Automatically Collected Information -->
                    <div class="ml-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i data-lucide="monitor" class="w-5 h-5 text-blue-500"></i>
                            C. Automatically Collected Information
                        </h3>
                        <ul class="space-y-2 ml-6">
                            <li class="flex items-start gap-3">
                                <i data-lucide="network" class="w-4 h-4 text-blue-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">IP address</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="network" class="w-4 h-4 text-blue-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Device information</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="network" class="w-4 h-4 text-blue-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Browser type</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="network" class="w-4 h-4 text-blue-500 mt-1 flex-shrink-0"></i>
                                <span class="text-gray-700">Usage data</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: How We Use Your Information -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">2</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="target" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">How We Use Your Information</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        We use your information to:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="heart-pulse" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Provide medical diagnosis, treatment, and healthcare services</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="calendar-check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Process appointments and patient registration</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="folder" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Maintain medical records</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="credit-card" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Process payments, insurance claims, and billing</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="trending-up" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Enhance website and system performance</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="scale" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Comply with legal and regulatory requirements</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 3: Legal Basis for Processing -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">3</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="gavel" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Legal Basis for Processing</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        We process your data under:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Explicit consent</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Performance of a medical or healthcare service</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Compliance with legal obligations</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Protection of vitally important interests</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 4: Data Sharing and Disclosure -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">4</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="share-2" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Data Sharing and Disclosure</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        We may share your information with:
                    </p>
                    <ul class="space-y-3 ml-8 mb-4">
                        <li class="flex items-start gap-3">
                            <i data-lucide="user-check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Attending physicians and authorized medical personnel</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="flask-conical" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Laboratories and diagnostic partners</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="shield" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Insurance providers, HMOs, and PhilHealth</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="building" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Government agencies (as required by law)</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="handshake" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Accredited third-party service providers</span>
                        </li>
                    </ul>
                    <div class="p-4 bg-green-50 border-l-4 border-green-400 rounded">
                        <p class="text-gray-700 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-5 h-5 text-green-600 flex-shrink-0"></i>
                            <strong>We never sell or misuse personal data.</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: Data Protection Measures -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">5</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="lock" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Data Protection Measures</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        Alvion implements:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="key" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Encryption and secure servers</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="hard-drive" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Physical security for medical records</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="user-cog" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Access controls and confidentiality agreements</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="eye" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">Regular audits and security monitoring</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 6: Data Retention -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">6</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="archive" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Data Retention</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        Medical records and personal information are retained:
                    </p>
                    <ul class="space-y-3 ml-8">
                        <li class="flex items-start gap-3">
                            <i data-lucide="file-check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">As required under DOH and NPC guidelines</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="file-check" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700">For as long as necessary to provide healthcare services</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section 7: Your Rights Under RA 10173 -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">7</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="scale" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Your Rights Under RA 10173</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        You may exercise the following rights:
                    </p>
                    <ul class="space-y-3 ml-8 mb-4">
                        <li class="flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to be informed</strong></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="eye" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to access</strong></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="edit" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to correct data</strong></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="ban" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to object</strong></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="trash-2" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to erasure or blocking</strong></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="download" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to data portability</strong></span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <span class="text-gray-700"><strong>Right to file a complaint with the NPC</strong></span>
                        </li>
                    </ul>
                    <div class="p-4 bg-teal-50 border-l-4 border-teal-400 rounded">
                        <p class="text-gray-700">
                            To exercise these rights, contact us through the details below.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 8: Cookies and Tracking Technologies -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">8</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="cookie" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Cookies and Tracking Technologies</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        We use cookies to improve user experience. You may manage them through your browser settings.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 9: Updates to the Privacy Policy -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">9</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="refresh-cw" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Updates to the Privacy Policy</h2>
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        Alvion may revise this policy from time to time. Updates will be posted on our website.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 10: Contact Information -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex items-start gap-4 mb-4">
                <span class="number-badge">10</span>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <i data-lucide="mail" class="section-icon"></i>
                        <h2 class="text-2xl font-bold text-gray-900">Contact Information</h2>
                    </div>
                    <p class="text-gray-700 mb-4 leading-relaxed">
                        For data privacy concerns, contact our Data Protection Officer (DPO):
                    </p>
                    <div class="bg-teal-50 rounded-lg p-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="building" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="font-semibold text-gray-900">Alvion Hospital – Data Privacy Office</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i data-lucide="mail" class="w-5 h-5 text-teal-custom mt-0.5 flex-shrink-0"></i>
                            <div>
                                <p class="text-gray-700">Email: <a href="mailto:dpo@alvionhealth.ph" class="text-teal-custom hover:underline">dpo@alvionhealth.ph</a></p>
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

