<p align="center">
  <img src="https://sdgs.un.org/themes/flavourites/flavourites_flavor/images/logo.svg" alt="UN SDG Logo" width="120"/>
</p>

<h1 align="center">🎓 SDG Thesis Classifier</h1>

<p align="center">
  <strong>An AI-powered tool for classifying academic research by UN Sustainable Development Goals</strong>
</p>

<p align="center">
  <a href="#-about">About</a> •
  <a href="#-features">Features</a> •
  <a href="#-the-model">The Model</a> •
  <a href="#-database-schema">Database</a> •
  <a href="#-getting-started">Getting Started</a> •
  <a href="#-team">Team</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Python-3.11+-blue?style=flat-square&logo=python" alt="Python"/>
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php" alt="PHP"/>
  <img src="https://img.shields.io/badge/Oracle-Database-F80000?style=flat-square&logo=oracle" alt="Oracle"/>
  <img src="https://img.shields.io/badge/HuggingFace-Transformers-FFD21E?style=flat-square&logo=huggingface" alt="HuggingFace"/>
  <img src="https://img.shields.io/badge/F1_Score-98.2%25-success?style=flat-square" alt="F1 Score"/>
</p>

---

## 📖 About

The **SDG Thesis Classifier** is a web-based application developed for Cal Poly Humboldt's library to automatically classify academic theses and dissertations according to the [United Nations Sustainable Development Goals (SDGs)](https://sdgs.un.org/goals).

### Why SDGs?

The **17 Sustainable Development Goals** are a universal call to action to end poverty, protect the planet, and ensure prosperity for all by 2030. Academic research plays a crucial role in achieving these goals, but discovering relevant research across disciplines has traditionally been challenging.

By tagging thesis abstracts with SDG classifications, we enable:

- 🔍 **Cross-disciplinary Discovery** — Researchers can find related work across departments
- 🤝 **Collaboration Opportunities** — Connect researchers working on similar sustainability challenges  
- 📊 **Impact Measurement** — Libraries can track and report on sustainability-related research output
- 🌍 **Global Alignment** — Link local research to global sustainability initiatives

### The 17 SDGs

| # | Goal | # | Goal |
|---|------|---|------|
| 1 | No Poverty | 10 | Reduced Inequalities |
| 2 | Zero Hunger | 11 | Sustainable Cities |
| 3 | Good Health & Well-being | 12 | Responsible Consumption |
| 4 | Quality Education | 13 | Climate Action |
| 5 | Gender Equality | 14 | Life Below Water |
| 6 | Clean Water & Sanitation | 15 | Life on Land |
| 7 | Affordable & Clean Energy | 16 | Peace, Justice & Strong Institutions |
| 8 | Decent Work & Economic Growth | | |
| 9 | Industry, Innovation & Infrastructure | | |

---

## ✨ Features

### 🏠 Single Abstract Classification
- Paste any thesis abstract into the text area
- Get instant AI-powered SDG predictions with confidence scores
- Interactive pie chart showing SDG distribution across the database
- Click SDGs on the chart to search for related theses

### 📦 Bulk Import
- Upload CSV files with thousands of thesis records
- **Pause/Resume functionality** — Long-running jobs can be paused and resumed later
- Progress is automatically saved to browser storage
- Real-time progress bar with time estimates

### 🔐 Admin Features
- Secure login with session management
- Admin users can approve classifications below the confidence threshold
- Manual tag editing with 100% confidence override

### 📊 Export & Reporting
- Filter by year, year range, or specific SDGs
- **Interactive pie chart** that updates based on filters
- **Download chart as PNG** for reports and presentations
- Export filtered data as CSV

### 🔍 Search
- Search approved theses by SDG tags
- View full details including abstract, author, and publication URL

---

## 🤖 The Model

### Architecture: ModernBERT Multi-Label Classifier

We fine-tuned **[ModernBERT-base](https://huggingface.co/answerdotai/ModernBERT-base)** for multi-label SDG classification. ModernBERT was chosen over other transformer models because:

- **Efficiency** — Optimized architecture with Flash Attention for faster inference
- **Modern Training** — Trained on more recent data than original BERT
- **Strong Baseline** — Excellent performance on text classification benchmarks

### Why Multi-Label?

Unlike single-label classification (where each thesis gets exactly ONE SDG), our **multi-label approach** recognizes that research often spans multiple sustainability goals. For example, a thesis on "Solar-powered water purification in rural communities" might relate to:
- SDG 6 (Clean Water)
- SDG 7 (Clean Energy)  
- SDG 11 (Sustainable Cities)

**Technical Implementation:**
- Uses `BCEWithLogitsLoss` instead of `CrossEntropyLoss`
- Each SDG is treated as an independent binary classification
- Output scores are independent probabilities (don't sum to 1)
- Threshold of **75%** for automatic approval

### Training Data

The model was trained on the **[OSDG Community Dataset](https://zenodo.org/records/5550238)** — a curated collection of ~17,000 text samples labeled with SDG tags by domain experts, filtered for samples with ≥70% annotator agreement.

### Performance Metrics

| Metric | Score |
|--------|-------|
| **F1 Micro** | 98.20% |
| **F1 Macro** | 92.02% |
| **F1 Weighted** | 98.16% |
| **Precision** | 98.15% |
| **Recall** | 98.20% |

### Confusion Matrix (Aggregated)

```
                    Predicted Neg    Predicted Pos
Actual Negative        26,468              187
Actual Positive           323            1,454
```

- **True Negatives:** 26,468 — Correctly predicted NOT this SDG
- **False Positives:** 187 — Incorrectly predicted this SDG
- **False Negatives:** 323 — Missed this SDG  
- **True Positives:** 1,454 — Correctly identified this SDG

### Confidence Distribution

The model shows strong separation between correct and incorrect predictions:
- ✅ **Correct predictions** cluster near 100% confidence
- ❌ **Missed SDGs** cluster near 0% confidence
- The 75% threshold effectively separates high-quality predictions

---

## 🗄️ Database Schema

The system uses an **Oracle Database** with the following entity-relationship structure:

### Entity-Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│   DEPARTMENTS   │       │     THESES      │       │  SDG_MAPPINGS   │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ department_id   │───┐   │ thesis_id (PK)  │───────│ mapping_id (PK) │
│ department_name │   └──>│ department_id   │       │ thesis_id (FK)  │
└─────────────────┘       │ title           │       │ pending_id (FK) │
                          │ author          │       │ sdg_number      │
                          │ publication_date│       │ confidence_score│
                          │ url             │       │ ranking         │
                          │ abstract (CLOB) │       │ classification_ │
                          │ keywords (CLOB) │       │   method        │
                          │ discipline      │       └─────────────────┘
                          │ created_at      │
                          └─────────────────┘
                                  │
                                  │ (similar structure)
                                  ▼
                          ┌─────────────────┐
                          │ PENDING_THESES  │
                          ├─────────────────┤
                          │ pending_id (PK) │
                          │ ... (same cols) │
                          │ status          │
                          └─────────────────┘
```

### Tables

| Table | Purpose |
|-------|---------|
| `THESES` | Approved thesis records with metadata |
| `PENDING_THESES` | Theses awaiting review (below confidence threshold) |
| `SDG_MAPPINGS` | Links theses to SDG tags with confidence scores |
| `DEPARTMENTS` | Academic department lookup table |

### Classification Methods

The `classification_method` field tracks how each SDG tag was assigned:

| Method | Description |
|--------|-------------|
| `ai_auto` | AI prediction with ≥75% confidence |
| `manual_edit` | User manually edited/added the tag |
| `admin_override` | Admin approved below-threshold prediction |

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.0+
- Oracle Database with OCI8 extension
- Web server (Apache/Nginx)
- Python 3.11+ (for data collection scripts)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/EpicormicSprout/soft-eng-458.git
   cd soft-eng-458
   ```

2. **Configure database connection**
   
   Edit `hum_conn_no_login.php` with your Oracle credentials.

3. **Set up the database**
   
   Run the SQL scripts in `database/` to create tables.

4. **Deploy to web server**
   
   Copy files to your web root directory.

### Data Collection Pipeline

To collect thesis abstracts from URLs:

1. Prepare a CSV with a `URL` column
2. Run the scraper:
   ```bash
   python3 scrape_abstracts.py
   ```
3. Review and clean the output CSV
4. Import via the Bulk Upload feature

See `General_Workflow_for_Abstracts.txt` for detailed instructions.

---

## 📁 Project Structure

```
soft-eng-458/
├── 📄 index.php              # Main application (single/bulk classification)
├── 📄 export_database.php    # Export page with charts
├── 📄 app.js                 # Frontend JavaScript
├── 📄 app.css                # Styles
├── 📄 save_thesis.php        # API: Save classifications
├── 📄 search_api.php         # API: Search theses
├── 📄 get_sdg_data.php       # API: Get SDG statistics
├── 📄 login_api.php          # API: Authentication
├── 📄 logout.php             # Session logout
├── 📄 dbFunctions.php        # Database utilities
├── 📄 hum_conn_no_login.php  # Database connection
│
├── 📁 data_collection/
│   ├── 📄 scrape_abstracts.py           # URL scraper for abstracts
│   └── 📄 General_Workflow_for_Abstracts.txt
│
├── 📁 model/
│   └── 📓 sdg_multilabel_training.ipynb  # Model training notebook
│
└── 📄 README.md
```

---

## 📈 Changes & Design Decisions

### Model Evolution

| Version | Architecture | Approach | Issue |
|---------|--------------|----------|-------|
| v1 | ModernBERT | Single-label | Labels sorted as strings ("1", "10", "11"...) causing misalignment |
| v2 | ModernBERT | **Multi-label** | ✅ Proper integer sorting, independent SDG probabilities |

### Key Design Decisions

1. **75% Confidence Threshold**
   - Balances automation with accuracy
   - Below-threshold predictions go to pending review
   - Admins can override to approve any prediction

2. **Pause/Resume for Bulk Upload**
   - Essential for processing 6,000+ records
   - Progress saved to localStorage every 25 records
   - Survives browser crashes and intentional pauses

3. **Multi-Label vs Single-Label**
   - Research often spans multiple SDGs
   - Independent probabilities allow nuanced classification
   - Top 3 predictions shown regardless of threshold

4. **Interactive Charts**
   - Pie chart legend shows full SDG names (e.g., "1. No Poverty")
   - Grayed-out appearance for deselected items (no strikethrough)
   - Downloadable as PNG for reports

---

## 👥 Team

**CS 458 – Software Engineering | Cal Poly Humboldt | Fall 2025**

| Role | Name |
|------|------|
| Team Leader | Hayden Weber |
| Lead Programmer | Marceline Vazquez Rios |
| Lead Designer | Nick Michel |
| Quality Assurance | Courtney Rowe |

---

## 📜 License

This project was developed for Cal Poly Humboldt's Library as part of the CS 458 Senior Capstone course.

---

<p align="center">
  <sub>Built for sustainable research discovery</sub>
</p>
