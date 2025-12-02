<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<!--
    SDG Thesis Classifier - Single Page Application
    Cal Poly Humboldt
    Last Modified: 2025-11-30
-->

<head>
    <title>SDG Thesis Classifier | Cal Poly Humboldt</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        session_start();
        require_once("hum_conn_no_login.php");
        require_once("dbFunctions.php");
        
        // Check if user is logged in
        $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['username']);
        $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? null;
    ?>
    <link href="app.css?v=<?= time() ?>" type="text/css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-banner">
            <div class="nav-container">
                <div class="banner-text">The Press at Cal Poly Humboldt</div>
            </div>
        </div>
        <div class="nav-main">
            <div class="nav-container">
                <div class="nav-links">
                    <a href="#about">About</a>
                    <a href="#how-it-works">How It Works</a>
                    <a href="#sdgs">SDGs</a>
                    <a href="#classifier" class="nav-toggle" data-target="classifier-section">Single Entry</a>
                    <a href="#bulk-import" class="nav-toggle" data-target="bulk-import-section">Bulk Import</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="export_database.php" class="nav-link">Export Database</a>
                        <span class="user-info">Welcome, <?= htmlspecialchars($userName) ?></span>
                        <a href="logout.php" class="btn-nav-logout">Logout</a>
                    <?php else: ?>
                        <a href="#login" class="nav-toggle" data-target="login-section">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="about">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">
                    Make Your Research Discoverable
                </h1>
                <p class="hero-subtitle">
                    Find collaborators across campus working toward the same UN Sustainable Development Goals.
                </p>
                <div class="hero-actions">
                    <a href="#classifier" class="btn-large btn-primary nav-toggle" data-target="classifier-section">
                        Start Classifying
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="#how-it-works" class="btn-large btn-secondary">Learn More</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="chart-preview-container">
                    <canvas id="sdgChart"></canvas>
                    <div class="chart-search-section">
                        <p class="search-prompt">Find Research by SDG</p>
                        <p id="selectedCount" class="selected-sdgs">Select up to 3 SDGs</p>
                        <button id="searchBySDG" class="btn-primary btn-search" disabled>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            Search Publications
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">31,600+</div>
                    <div class="stat-label">Cal Poly Humboldt Theses Publications </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">16</div>
                    <div class="stat-label">UN SDG Categories</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Classification Accuracy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">&lt; 5s</div>
                    <div class="stat-label">Average Processing Time</div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Simple, fast, and accurate SDG classification in three steps</p>
            
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <h3 class="step-title">1. Enter Metadata</h3>
                    <p class="step-description">
                        Provide basic information about your research: title, author, publication date, 
                        and department.
                    </p>
                </div>

                <div class="step-card">
                    <div class="step-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="9"></line>
                            <line x1="9" y1="15" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <h3 class="step-title">2. Submit Abstract</h3>
                    <p class="step-description">
                        Paste your thesis or research abstract into the text field and run the AI classifier.
                    </p>
                </div>

                <div class="step-card">
                    <div class="step-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <h3 class="step-title">3. Review & Save</h3>
                    <p class="step-description">
                        Get instant SDG classifications with confidence scores. Review, edit if needed, 
                        and save to the database.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- SDG Section with Tooltips -->
    <section id="sdgs" class="sdg-section">
        <div class="container">
            <h2 class="section-title">United Nations Sustainable Development Goals</h2>
            <p class="section-subtitle">
                The 2030 Agenda for Sustainable Development, adopted by all United Nations Member States in 2015, 
                provides a shared blueprint for peace and prosperity for people and the planet.
            </p>
            
            <div class="sdg-full-grid">
                <div class="sdg-item" style="background: #E5243B;" data-sdg="1">
                    <span class="sdg-num">1</span>
                    <span class="sdg-label">No Poverty</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 1: No Poverty</div>
                        <div class="sdg-tooltip-desc">End poverty in all its forms everywhere. This includes ensuring social protection for the poor, increasing access to basic services, and supporting people affected by climate-related extreme events.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #DDA63A;" data-sdg="2">
                    <span class="sdg-num">2</span>
                    <span class="sdg-label">Zero Hunger</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 2: Zero Hunger</div>
                        <div class="sdg-tooltip-desc">End hunger, achieve food security and improved nutrition, and promote sustainable agriculture. This includes ensuring access to safe and nutritious food year-round.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #4C9F38;" data-sdg="3">
                    <span class="sdg-num">3</span>
                    <span class="sdg-label">Good Health</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 3: Good Health and Well-being</div>
                        <div class="sdg-tooltip-desc">Ensure healthy lives and promote well-being for all at all ages. This covers maternal health, communicable diseases, mental health, and universal health coverage.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #C5192D;" data-sdg="4">
                    <span class="sdg-num">4</span>
                    <span class="sdg-label">Quality Education</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 4: Quality Education</div>
                        <div class="sdg-tooltip-desc">Ensure inclusive and equitable quality education and promote lifelong learning opportunities for all. This includes free primary and secondary education.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #FF3A21;" data-sdg="5">
                    <span class="sdg-num">5</span>
                    <span class="sdg-label">Gender Equality</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 5: Gender Equality</div>
                        <div class="sdg-tooltip-desc">Achieve gender equality and empower all women and girls. This includes ending discrimination and violence against women, and ensuring equal participation in leadership.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #26BDE2;" data-sdg="6">
                    <span class="sdg-num">6</span>
                    <span class="sdg-label">Clean Water</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 6: Clean Water and Sanitation</div>
                        <div class="sdg-tooltip-desc">Ensure availability and sustainable management of water and sanitation for all. This includes safe drinking water, adequate sanitation, and reducing water pollution.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #FCC30B;" data-sdg="7">
                    <span class="sdg-num">7</span>
                    <span class="sdg-label">Clean Energy</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 7: Affordable and Clean Energy</div>
                        <div class="sdg-tooltip-desc">Ensure access to affordable, reliable, sustainable, and modern energy for all. This includes increasing renewable energy and improving energy efficiency.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #A21942;" data-sdg="8">
                    <span class="sdg-num">8</span>
                    <span class="sdg-label">Decent Work</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 8: Decent Work and Economic Growth</div>
                        <div class="sdg-tooltip-desc">Promote sustained, inclusive, and sustainable economic growth, full and productive employment, and decent work for all.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #FD6925;" data-sdg="9">
                    <span class="sdg-num">9</span>
                    <span class="sdg-label">Innovation</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 9: Industry, Innovation and Infrastructure</div>
                        <div class="sdg-tooltip-desc">Build resilient infrastructure, promote inclusive and sustainable industrialization, and foster innovation.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #DD1367;" data-sdg="10">
                    <span class="sdg-num">10</span>
                    <span class="sdg-label">Reduced Inequalities</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 10: Reduced Inequalities</div>
                        <div class="sdg-tooltip-desc">Reduce inequality within and among countries. This includes promoting social, economic, and political inclusion regardless of status.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #FD9D24;" data-sdg="11">
                    <span class="sdg-num">11</span>
                    <span class="sdg-label">Sustainable Cities</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 11: Sustainable Cities and Communities</div>
                        <div class="sdg-tooltip-desc">Make cities and human settlements inclusive, safe, resilient, and sustainable. This includes affordable housing and sustainable transport.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #BF8B2E;" data-sdg="12">
                    <span class="sdg-num">12</span>
                    <span class="sdg-label">Responsible Consumption</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 12: Responsible Consumption and Production</div>
                        <div class="sdg-tooltip-desc">Ensure sustainable consumption and production patterns. This includes reducing waste and promoting sustainable practices.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #3F7E44;" data-sdg="13">
                    <span class="sdg-num">13</span>
                    <span class="sdg-label">Climate Action</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 13: Climate Action</div>
                        <div class="sdg-tooltip-desc">Take urgent action to combat climate change and its impacts. This includes strengthening resilience to climate hazards.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #0A97D9;" data-sdg="14">
                    <span class="sdg-num">14</span>
                    <span class="sdg-label">Life Below Water</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 14: Life Below Water</div>
                        <div class="sdg-tooltip-desc">Conserve and sustainably use the oceans, seas, and marine resources for sustainable development.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #56C02B;" data-sdg="15">
                    <span class="sdg-num">15</span>
                    <span class="sdg-label">Life on Land</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 15: Life on Land</div>
                        <div class="sdg-tooltip-desc">Protect, restore, and promote sustainable use of terrestrial ecosystems, combat desertification, and halt biodiversity loss.</div>
                    </div>
                </div>
                <div class="sdg-item" style="background: #00689D;" data-sdg="16">
                    <span class="sdg-num">16</span>
                    <span class="sdg-label">Peace & Justice</span>
                    <div class="sdg-tooltip">
                        <div class="sdg-tooltip-title">SDG 16: Peace, Justice and Strong Institutions</div>
                        <div class="sdg-tooltip-desc">Promote peaceful and inclusive societies, provide access to justice for all, and build effective, accountable institutions.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================ -->
    <!-- COLLAPSIBLE SECTION: Single Entry Classifier -->
    <!-- ============================================ -->
    <section id="classifier" class="collapsible-section">
        <div class="collapsible-header" data-target="classifier-section">
            <div class="container">
                <div class="collapsible-header-content">
                    <div class="collapsible-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <div class="collapsible-title-area">
                        <h2 class="collapsible-title">Single Entry Classifier</h2>
                        <p class="collapsible-subtitle">Classify a single thesis abstract against UN SDGs</p>
                    </div>
                    <div class="collapsible-toggle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="collapsible-content" id="classifier-section">
            <div class="container">
                <div class="classifier-container">
                    <!-- Step 1: Metadata -->
                    <div class="classifier-step" id="step1">
                        <div class="classifier-step-header">
                            <div class="classifier-step-number">1</div>
                            <div class="classifier-step-info">
                                <h3>Publication Metadata</h3>
                                <p>Enter required information about your publication</p>
                            </div>
                        </div>
                        
                        <form id="metadataForm" class="modern-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="metaTitle">Title <span class="required">*</span></label>
                                    <input type="text" id="metaTitle" name="metaTitle" required placeholder="Enter publication title" />
                                </div>
                                
                                <div class="form-group">
                                    <label for="metaAuthor">Author <span class="required">*</span></label>
                                    <input type="text" id="metaAuthor" name="metaAuthor" required placeholder="Last Name, First Name (e.g., Smith, John)" />
                                    <small class="form-hint">Format: Last Name, First Name</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="metaPubDate">Publication Date <span class="required">*</span></label>
                                    <input type="date" id="metaPubDate" name="metaPubDate" required />
                                </div>
                                
                                <div class="form-group">
                                    <label for="metaDepartment">Department <span class="optional">(optional)</span></label>
                                    <select id="metaDepartment" name="metaDepartment">
                                        <option value="">-- Select Department --</option>
                                        <option value="Anthropology">Anthropology</option>
                                        <option value="Art">Art</option>
                                        <option value="Biology">Biology</option>
                                        <option value="Business">Business</option>
                                        <option value="Chemistry">Chemistry</option>
                                        <option value="Computer Science">Computer Science</option>
                                        <option value="Economics">Economics</option>
                                        <option value="Education">Education</option>
                                        <option value="Engineering">Engineering</option>
                                        <option value="English">English</option>
                                        <option value="Environmental Science">Environmental Science</option>
                                        <option value="Geography">Geography</option>
                                        <option value="Geology">Geology</option>
                                        <option value="History">History</option>
                                        <option value="Mathematics">Mathematics</option>
                                        <option value="Music">Music</option>
                                        <option value="Philosophy">Philosophy</option>
                                        <option value="Physics">Physics</option>
                                        <option value="Political Science">Political Science</option>
                                        <option value="Psychology">Psychology</option>
                                        <option value="Sociology">Sociology</option>
                                        <option value="Theatre Arts">Theatre Arts</option>
                                        <option value="Wildlife">Wildlife</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="metaUrl">URL <span class="optional">(optional)</span></label>
                                    <input type="url" id="metaUrl" name="metaUrl" placeholder="https://example.com/publication" />
                                </div>
                                
                                <div class="form-group">
                                    <label for="metaDiscipline">Discipline <span class="optional">(optional)</span></label>
                                    <input type="text" id="metaDiscipline" name="metaDiscipline" placeholder="e.g., Marine Biology" />
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="metaKeywords">Keywords <span class="optional">(optional)</span></label>
                                    <input type="text" id="metaKeywords" name="metaKeywords" placeholder="Enter keywords separated by commas" />
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Abstract Input -->
                    <div class="classifier-step" id="step2">
                        <div class="classifier-step-header">
                            <div class="classifier-step-number">2</div>
                            <div class="classifier-step-info">
                                <h3>Abstract Classification</h3>
                                <p>Paste your abstract and run the AI classifier</p>
                            </div>
                        </div>
                        
                        <form id="predictForm" class="modern-form">
                            <div class="form-group full-width">
                                <label for="thesisAbstract">Thesis Abstract <span class="required">*</span></label>
                                <textarea name="thesisAbstract" id="thesisAbstract" 
                                          placeholder="Paste your thesis or research abstract here..."
                                          rows="10" required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button class="btn-primary btn-large" id="predictBtn" type="submit">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                    </svg>
                                    Run Classification
                                </button>
                                <span id="status" class="status-text"></span>
                            </div>
                        </form>
                    </div>

                    <!-- Step 3: Results -->
                    <div class="classifier-step" id="step3">
                        <div class="classifier-step-header">
                            <div class="classifier-step-number">3</div>
                            <div class="classifier-step-info">
                                <h3>Classification Results</h3>
                                <p>Review and refine your SDG classifications (75% threshold to save)</p>
                            </div>
                        </div>
                        
                        <div id="errorBox" class="alert alert-error hidden"></div>
                        
                        <div id="resultsBox" class="hidden">
                            <div class="results-header">
                                <div>
                                    <h4>SDG Predictions</h4>
                                    <p class="threshold-info">Tags must meet 75% confidence to be saved. Manual edits are set to 100%.</p>
                                </div>
                                <div class="results-actions">
                                    <button id="edit" class="btn-secondary" type="button">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button id="markNoRelevant" class="btn-secondary" type="button">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="15" y1="9" x2="9" y2="15"></line>
                                            <line x1="9" y1="9" x2="15" y2="15"></line>
                                        </svg>
                                        No Relevant Tags
                                    </button>
                                    <button id="save" class="btn-primary" type="button">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                        Save
                                    </button>
                                </div>
                            </div>
                            
                            <div id="resultsContainer" class="predictions-grid"></div>
                        </div>
                        
                        <p id="emptyMsg" class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Run a prediction to see results here
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================ -->
    <!-- COLLAPSIBLE SECTION: Bulk Import -->
    <!-- ============================================ -->
    <section id="bulk-import" class="collapsible-section">
        <div class="collapsible-header" data-target="bulk-import-section">
            <div class="container">
                <div class="collapsible-header-content">
                    <div class="collapsible-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <div class="collapsible-title-area">
                        <h2 class="collapsible-title">Bulk CSV Import</h2>
                        <p class="collapsible-subtitle">Import multiple thesis abstracts for classification</p>
                    </div>
                    <div class="collapsible-toggle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="collapsible-content" id="bulk-import-section">
            <div class="container">
                <?php if (!$isLoggedIn): ?>
                <!-- Login Required Message -->
                <div class="login-required-box">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <h3>Authentication Required</h3>
                    <p>Please log in to access the bulk import feature.</p>
                    <a href="#login" class="btn-primary btn-large nav-toggle" data-target="login-section">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        Login to Continue
                    </a>
                </div>
                <?php else: ?>
                <!-- Bulk Import Content -->
                <div class="bulk-import-container">
                    <!-- Upload Step -->
                    <div class="classifier-step" id="bulkStep1">
                        <div class="classifier-step-header">
                            <div class="classifier-step-number">1</div>
                            <div class="classifier-step-info">
                                <h3>Upload CSV File</h3>
                                <p>Select a CSV file containing thesis metadata and abstracts</p>
                            </div>
                        </div>

                        <div class="csv-info-box">
                            <h4>Expected CSV Format:</h4>
                            <ul>
                                <li><strong>Required columns:</strong> Last Name, First Name, Year, Title, Abstract</li>
                                <li><strong>Optional columns:</strong> URL</li>
                                <li><strong>Year format:</strong> YYYY (e.g., 2024)</li>
                            </ul>
                            <p class="csv-example">Example: <code>Last Name,First Name,Year,Title,URL,Abstract</code></p>
                        </div>

                        <form id="uploadForm" class="modern-form" enctype="multipart/form-data">
                            <div class="form-group full-width">
                                <label for="csvFile">CSV File <span class="required">*</span></label>
                                <input type="file" id="csvFile" name="csvFile" accept=".csv" required />
                            </div>

                            <div class="form-actions">
                                <button class="btn-primary btn-large" id="uploadBtn" type="submit">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    Upload &amp; Preview
                                </button>
                                <span id="uploadStatus" class="status-text"></span>
                            </div>
                        </form>
                    </div>

                    <!-- Preview Step (hidden initially) -->
                    <div class="classifier-step hidden" id="bulkStep2">
                        <div class="classifier-step-header">
                            <div class="classifier-step-number">2</div>
                            <div class="classifier-step-info">
                                <h3>Preview &amp; Validate</h3>
                                <p>Review imported records before processing</p>
                            </div>
                        </div>

                        <div id="validationSummary" class="validation-summary"></div>

                        <div class="preview-controls">
                            <button id="selectValidBtn" class="btn-secondary">Select Valid</button>
                            <button id="clearSelectionBtn" class="btn-secondary">Clear Selection</button>
                            <span id="selectionCount" class="selection-count">0 records selected</span>
                        </div>

                        <div id="previewTable" class="preview-table-container"></div>

                        <div class="form-actions" style="flex-wrap: wrap; gap: 10px;">
                            <button class="btn-primary btn-large" id="processBtn" type="button" disabled>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                                Process Selected Records
                            </button>
                            <button class="btn-secondary hidden" id="pauseBtn" type="button">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="6" y="4" width="4" height="16"></rect>
                                    <rect x="14" y="4" width="4" height="16"></rect>
                                </svg>
                                Pause
                            </button>
                            <button class="btn-danger hidden" id="stopBtn" type="button">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                </svg>
                                Stop
                            </button>
                            <span id="processStatus" class="status-text"></span>
                        </div>
                        <p style="font-size: 0.85rem; color: #666; margin-top: 10px;">
                            <strong>Tip:</strong> For large batches (1000+ records), processing may take a while. 
                            You can pause/stop and resume later - progress is automatically saved.
                        </p>
                    </div>

                    <!-- Results Step (hidden initially) -->
                    <div class="classifier-step hidden" id="bulkStep3">
                        <div class="classifier-step-header">
                            <div class="classifier-step-number">3</div>
                            <div class="classifier-step-info">
                                <h3>Processing Results</h3>
                                <p>Review and edit classifications before saving</p>
                            </div>
                        </div>

                        <div id="bulkResultsTable" class="results-table-container"></div>

                        <div class="form-actions">
                            <button class="btn-primary btn-large" id="saveAllBtn" type="button" disabled>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save All to Database
                            </button>
                            <button class="btn-secondary btn-large" id="downloadResultsBtn" type="button" disabled>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                Download CSV
                            </button>
                            <span id="saveStatus" class="status-text"></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============================================ -->
    <!-- COLLAPSIBLE SECTION: Login -->
    <!-- ============================================ -->
    <section id="login" class="collapsible-section">
        <div class="collapsible-header" data-target="login-section">
            <div class="container">
                <div class="collapsible-header-content">
                    <div class="collapsible-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                    </div>
                    <div class="collapsible-title-area">
                        <h2 class="collapsible-title">Staff Login</h2>
                        <p class="collapsible-subtitle">Access bulk import and administrative features</p>
                    </div>
                    <div class="collapsible-toggle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="collapsible-content" id="login-section">
            <div class="container">
                <div class="login-form-container">
                    <form id="loginForm" class="modern-form login-form">
                        <div class="form-group">
                            <label for="loginUsername">Username</label>
                            <input type="text" id="loginUsername" name="username" required placeholder="Enter your username" />
                        </div>
                        
                        <div class="form-group">
                            <label for="loginPassword">Password</label>
                            <input type="password" id="loginPassword" name="password" required placeholder="Enter your password" />
                        </div>
                        
                        <div id="loginError" class="alert alert-error hidden"></div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary btn-large btn-full">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                    <polyline points="10 17 15 12 10 7"></polyline>
                                    <line x1="15" y1="12" x2="3" y2="12"></line>
                                </svg>
                                Login
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================ -->
    <!-- COLLAPSIBLE SECTION: Search Results -->
    <!-- ============================================ -->
    <section id="search-results" class="collapsible-section">
        <div class="collapsible-header" data-target="search-results-section">
            <div class="container">
                <div class="collapsible-header-content">
                    <div class="collapsible-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </div>
                    <div class="collapsible-title-area">
                        <h2 class="collapsible-title">Search Results</h2>
                        <p class="collapsible-subtitle" id="searchResultsSubtitle">Select SDGs from the chart above to search</p>
                    </div>
                    <div class="collapsible-toggle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="collapsible-content" id="search-results-section">
            <div class="container">
                <div id="searchResultsContainer">
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <p>Select SDGs from the chart and click "Search Publications" to see results</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">Classify Your Research</h2>
                <p class="cta-subtitle">Connect your work with Sustainable Development Goals</p>
                <a href="#classifier" class="btn-large btn-primary nav-toggle" data-target="classifier-section">Get Started Now</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>About This Project</h4>
                    <p>Senior Capstone Project by Nick Michel, Courtney Rowe, Hayden Weber, and Marceline Vasquez Rios</p>
                </div>
                <div class="footer-section">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="https://sdgs.un.org/goals" target="_blank">UN SDGs Official Site</a></li>
                        <li><a href="https://digitalcommons.humboldt.edu/" target="_blank">Humboldt Digital Commons</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>Cal Poly Humboldt<br>Computer Science Department</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Cal Poly Humboldt. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Edit Modal -->
    <div id="editModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit SDG Predictions</h3>
                <button id="closeModal" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-info">Manual edits will be set to 100% confidence.</p>
                <div id="editorRows"></div>
            </div>
            <div class="modal-footer">
                <button id="cancelEdits" class="btn-secondary">Cancel</button>
                <button id="saveEdits" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Bulk Edit Modal -->
    <div id="bulkEditModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="bulkEditTitle">Edit SDG Tags</h3>
                <button id="closeBulkModal" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-info">Manual edits will be set to 100% confidence.</p>
                <div id="bulkEditorRows"></div>
            </div>
            <div class="modal-footer">
                <button id="cancelBulkEdits" class="btn-secondary">Cancel</button>
                <button id="saveBulkEdits" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="module" src="app.js?v=<?= time() ?>"></script>
</body>
</html>
