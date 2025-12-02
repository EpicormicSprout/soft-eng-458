/**
 * SDG Thesis Classifier - Main Application JavaScript
 * Single page app with collapsible sections
 * Last Modified: 2025-11-30
 */

// Import Gradio client (same as original working code)
import { client as hfClient } from "https://cdn.jsdelivr.net/npm/@gradio/client/+esm";

// ============================================
// CONFIGURATION
// ============================================
const CONFIG = {
    SPACE_OWNER: "SterlingWork",
    SPACE_NAME: "SDGclassifier",
    HF_TOKEN: null,
    MIN_CONFIDENCE_THRESHOLD: 0.75,  // 75% threshold
    MANUAL_EDIT_CONFIDENCE: 1.0,     // Manual edits set to 100%
    BATCH_SIZE: 5,
    API_DELAY: 3000
};

// SDG Data with names and colors
const SDG_DATA = {
    1:  { name: "No Poverty", color: "#E5243B" },
    2:  { name: "Zero Hunger", color: "#DDA63A" },
    3:  { name: "Good Health", color: "#4C9F38" },
    4:  { name: "Quality Education", color: "#C5192D" },
    5:  { name: "Gender Equality", color: "#FF3A21" },
    6:  { name: "Clean Water", color: "#26BDE2" },
    7:  { name: "Clean Energy", color: "#FCC30B" },
    8:  { name: "Decent Work", color: "#A21942" },
    9:  { name: "Innovation", color: "#FD6925" },
    10: { name: "Reduced Inequalities", color: "#DD1367" },
    11: { name: "Sustainable Cities", color: "#FD9D24" },
    12: { name: "Responsible Consumption", color: "#BF8B2E" },
    13: { name: "Climate Action", color: "#3F7E44" },
    14: { name: "Life Below Water", color: "#0A97D9" },
    15: { name: "Life on Land", color: "#56C02B" },
    16: { name: "Peace & Justice", color: "#00689D" }
};

// ============================================
// STATE
// ============================================
let state = {
    chartInstance: null,
    chartSelectedSDGs: new Set(),
    currentPreds: null,
    originalPreds: null,
    csvData: [],
    selectedRecords: new Set(),
    processedResults: [],
    editingBulkIndex: null,
    searchResults: [],
    isPaused: false,
    shouldStop: false
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    initCollapsibleSections();
    initNavigation();
    initChart();
    initClassifier();
    initBulkImport();
    initLogin();
    initModals();
    
    // Handle hash on page load
    handleHashChange();
});

// ============================================
// COLLAPSIBLE SECTIONS
// ============================================
function initCollapsibleSections() {
    const headers = document.querySelectorAll('.collapsible-header');
    
    headers.forEach(header => {
        header.addEventListener('click', () => {
            const targetId = header.dataset.target;
            const section = header.closest('.collapsible-section');
            
            // Toggle this section
            section.classList.toggle('expanded');
        });
    });
}

function expandSection(sectionId) {
    const content = document.getElementById(sectionId);
    if (content) {
        const section = content.closest('.collapsible-section');
        if (section && !section.classList.contains('expanded')) {
            section.classList.add('expanded');
        }
    }
}

function collapseSection(sectionId) {
    const content = document.getElementById(sectionId);
    if (content) {
        const section = content.closest('.collapsible-section');
        if (section) {
            section.classList.remove('expanded');
        }
    }
}

// ============================================
// NAVIGATION
// ============================================
function initNavigation() {
    // Handle nav toggle links
    document.querySelectorAll('.nav-toggle').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.dataset.target;
            
            if (targetId) {
                expandSection(targetId);
                
                // Scroll to section
                const section = document.getElementById(targetId)?.closest('.collapsible-section');
                if (section) {
                    setTimeout(() => {
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            }
        });
    });
    
    // Handle hash changes
    window.addEventListener('hashchange', handleHashChange);
}

function handleHashChange() {
    const hash = window.location.hash.slice(1);
    
    if (hash) {
        // Map hash to section IDs
        const sectionMap = {
            'classifier': 'classifier-section',
            'bulk-import': 'bulk-import-section',
            'login': 'login-section',
            'search-results': 'search-results-section'
        };
        
        const sectionId = sectionMap[hash];
        if (sectionId) {
            expandSection(sectionId);
            
            const section = document.getElementById(sectionId)?.closest('.collapsible-section');
            if (section) {
                setTimeout(() => {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    }
}

// ============================================
// CHART INITIALIZATION
// ============================================
function initChart() {
    const canvas = document.getElementById('sdgChart');
    if (!canvas) return;

    // Fetch SDG data from server
    fetch('get_sdg_data.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                createChart(canvas, data.data);
            } else {
                console.error('Failed to load SDG data');
                createChart(canvas, Array(16).fill(100)); // Fallback
            }
        })
        .catch(err => {
            console.error('Error fetching SDG data:', err);
            createChart(canvas, Array(16).fill(100)); // Fallback
        });
}

function createChart(canvas, chartData) {
    const ctx = canvas.getContext('2d');
    
    const labels = Object.entries(SDG_DATA).map(([n, s]) => `${n}. ${s.name}`);
    const colors = Object.values(SDG_DATA).map(s => s.color);
    
    state.chartInstance = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: chartData,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.35,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 12,
                        usePointStyle: true,
                        font: { size: 11 },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const meta = chart.getDatasetMeta(0);
                                    const style = meta.controller.getStyle(i);
                                    const sdgNum = i + 1;
                                    const isSelected = state.chartSelectedSDGs.size === 0 || state.chartSelectedSDGs.has(sdgNum);
                                    
                                    return {
                                        text: label,
                                        fillStyle: isSelected ? style.backgroundColor : '#e0e0e0',
                                        strokeStyle: style.borderColor,
                                        lineWidth: style.borderWidth,
                                        pointStyle: 'circle',
                                        hidden: false,
                                        index: i,
                                        fontColor: isSelected ? '#333' : '#999'
                                    };
                                });
                            }
                            return [];
                        }
                    },
                    onClick: function(event, legendItem, legend) {
                        const sdgNum = legendItem.index + 1;
                        if (state.chartSelectedSDGs.has(sdgNum)) {
                            state.chartSelectedSDGs.delete(sdgNum);
                        } else if (state.chartSelectedSDGs.size < 3) {
                            state.chartSelectedSDGs.add(sdgNum);
                        }
                        updateChartSelection();
                        updateSelectedCount();
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const sdgNum = context[0].dataIndex + 1;
                            return `SDG ${sdgNum}: ${SDG_DATA[sdgNum].name}`;
                        },
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((value / total) * 100).toFixed(1);
                            return `${value} publications (${pct}%)`;
                        }
                    }
                }
            },
            onClick: handleChartClick
        }
    });
}

function handleChartClick(event, elements) {
    if (!elements || elements.length === 0) return;
    
    const index = elements[0].index;
    const sdgNum = index + 1;
    
    if (state.chartSelectedSDGs.has(sdgNum)) {
        state.chartSelectedSDGs.delete(sdgNum);
    } else if (state.chartSelectedSDGs.size < 3) {
        state.chartSelectedSDGs.add(sdgNum);
    }
    
    updateChartSelection();
    updateSelectedCount();
}

function updateChartSelection() {
    if (!state.chartInstance) return;
    
    const colors = Object.values(SDG_DATA).map((s, i) => {
        const sdgNum = i + 1;
        if (state.chartSelectedSDGs.size === 0) return s.color;
        return state.chartSelectedSDGs.has(sdgNum) ? s.color : '#e0e0e0';
    });
    
    state.chartInstance.data.datasets[0].backgroundColor = colors;
    
    // Force legend to regenerate with updated styles
    state.chartInstance.update();
}

function updateSelectedCount() {
    const countEl = document.getElementById('selectedCount');
    const searchBtn = document.getElementById('searchBySDG');
    
    if (!countEl || !searchBtn) return;
    
    const count = state.chartSelectedSDGs.size;
    
    if (count === 0) {
        countEl.textContent = 'Select up to 3 SDGs';
        searchBtn.disabled = true;
    } else {
        const sdgNames = Array.from(state.chartSelectedSDGs)
            .map(n => `SDG ${n}`)
            .join(', ');
        countEl.textContent = `Selected: ${sdgNames}`;
        searchBtn.disabled = false;
    }
    
    // Add click handler for search button
    searchBtn.onclick = performSearch;
}

// ============================================
// SEARCH FUNCTIONALITY
// ============================================
async function performSearch() {
    if (state.chartSelectedSDGs.size === 0) return;
    
    const sdgs = Array.from(state.chartSelectedSDGs).join(',');
    const searchBtn = document.getElementById('searchBySDG');
    const resultsContainer = document.getElementById('searchResultsContainer');
    const subtitle = document.getElementById('searchResultsSubtitle');
    
    searchBtn.disabled = true;
    searchBtn.innerHTML = `
        <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="20"></circle>
        </svg>
        Searching...
    `;
    
    try {
        const response = await fetch(`search_api.php?sdgs=${sdgs}`);
        const responseText = await response.text();
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseErr) {
            throw new Error(`Invalid response from server: ${responseText.substring(0, 200)}`);
        }
        
        // Check for error response
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Expand search results section
        expandSection('search-results-section');
        
        // Scroll to results after a short delay to let expansion complete
        setTimeout(() => {
            const searchResultsSection = document.getElementById('search-results');
            if (searchResultsSection) {
                searchResultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 300);
        
        // Update subtitle
        const sdgNames = Array.from(state.chartSelectedSDGs).map(n => `SDG ${n}`).join(', ');
        subtitle.textContent = `Showing results for: ${sdgNames}`;
        
        // Render results
        if (!Array.isArray(data) || data.length === 0) {
            resultsContainer.innerHTML = `
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <p>No publications found matching the selected SDGs</p>
                </div>
            `;
        } else {
            resultsContainer.innerHTML = `
                <div class="search-results-header">
                    <p>Found ${data.length} publication${data.length === 1 ? '' : 's'}</p>
                    <button class="btn-secondary" onclick="exportSearchResults()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Export CSV
                    </button>
                </div>
                <div class="search-results-list">
                    ${data.map(thesis => renderThesisCard(thesis)).join('')}
                </div>
            `;
            
            // Store results for export
            state.searchResults = data;
        }
        
    } catch (err) {
        console.error('Search error:', err);
        resultsContainer.innerHTML = `
            <div class="alert alert-error">
                <strong>Search Error:</strong> ${escapeHtml(err.message)}<br>
                <small>Please check that the database connection is working and try again.</small>
            </div>
        `;
        
        // Still expand section to show error
        expandSection('search-results-section');
    }
    
    searchBtn.disabled = false;
    searchBtn.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
        Search Publications
    `;
}

// Export search results to CSV
window.exportSearchResults = function() {
    if (!state.searchResults || state.searchResults.length === 0) {
        alert('No results to export');
        return;
    }
    
    const rows = [['Title', 'Author', 'Publication Date', 'Department', 'URL', 'SDGs', 'Abstract']];
    
    state.searchResults.forEach(t => {
        rows.push([
            `"${(t.title || '').replace(/"/g, '""')}"`,
            `"${(t.author || '').replace(/"/g, '""')}"`,
            t.publication_date || '',
            `"${(t.department_name || '').replace(/"/g, '""')}"`,
            t.url || '',
            t.sdg_numbers || t.sdg_labels || '',
            `"${(t.abstract || '').replace(/"/g, '""').substring(0, 500)}"`
        ]);
    });
    
    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `sdg_search_results_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    
    URL.revokeObjectURL(url);
};

function renderThesisCard(thesis) {
    const abstract = thesis.abstract || '';
    const truncated = abstract.length > 250 ? abstract.slice(0, 250) + '...' : abstract;
    
    return `
        <div class="thesis-card">
            <h4 class="thesis-title">${escapeHtml(thesis.title || 'Untitled')}</h4>
            <div class="thesis-meta">
                <span><strong>Author:</strong> ${escapeHtml(thesis.author || 'Unknown')}</span>
                <span><strong>Date:</strong> ${thesis.publication_date || 'N/A'}</span>
                ${thesis.department ? `<span><strong>Dept:</strong> ${escapeHtml(thesis.department)}</span>` : ''}
            </div>
            ${truncated ? `<p class="thesis-abstract">${escapeHtml(truncated)}</p>` : ''}
            ${thesis.url ? `<a href="${thesis.url}" target="_blank" class="btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">View Publication</a>` : ''}
        </div>
    `;
}

// ============================================
// SINGLE ENTRY CLASSIFIER
// ============================================
function initClassifier() {
    const predictForm = document.getElementById('predictForm');
    const editBtn = document.getElementById('edit');
    const saveBtn = document.getElementById('save');
    const markNoRelevantBtn = document.getElementById('markNoRelevant');
    
    if (predictForm) {
        predictForm.addEventListener('submit', handlePrediction);
    }
    
    if (editBtn) {
        editBtn.addEventListener('click', openEditModal);
    }
    
    if (saveBtn) {
        saveBtn.addEventListener('click', handleSave);
    }
    
    if (markNoRelevantBtn) {
        markNoRelevantBtn.addEventListener('click', handleMarkNoRelevant);
    }
}

async function handlePrediction(e) {
    e.preventDefault();
    
    const abstract = document.getElementById('thesisAbstract').value.trim();
    if (!abstract) {
        alert('Please enter an abstract');
        return;
    }
    
    const status = document.getElementById('status');
    const predictBtn = document.getElementById('predictBtn');
    const resultsBox = document.getElementById('resultsBox');
    const errorBox = document.getElementById('errorBox');
    const emptyMsg = document.getElementById('emptyMsg');
    
    status.textContent = 'Connecting to AI model...';
    predictBtn.disabled = true;
    resultsBox.classList.add('hidden');
    errorBox.classList.add('hidden');
    emptyMsg.classList.add('hidden');
    
    try {
        // Use Gradio client like original working code
        const preds = await runGradioPredict(abstract);
        
        state.currentPreds = preds;
        state.originalPreds = JSON.parse(JSON.stringify(preds));
        
        renderPredictions(preds);
        resultsBox.classList.remove('hidden');
        status.textContent = 'Classification complete!';
        
    } catch (err) {
        console.error('Prediction error:', err);
        // Show detailed error message for debugging
        const errorMsg = err.message || String(err);
        errorBox.innerHTML = `
            <strong>Classification Error:</strong><br>
            ${escapeHtml(errorMsg)}<br><br>
            <small>If this persists, try refreshing the page or check if the HuggingFace Space is online.</small>
        `;
        errorBox.classList.remove('hidden');
        status.textContent = 'Classification failed';
    }
    
    predictBtn.disabled = false;
}

// Use Gradio client (same approach as original working classifier.js)
async function runGradioPredict(text) {
    const app = await hfClient(
        `${CONFIG.SPACE_OWNER}/${CONFIG.SPACE_NAME}`,
        CONFIG.HF_TOKEN ? { hf_token: CONFIG.HF_TOKEN } : undefined
    );
    
    const res = await app.predict("/predict", { text });
    
    let scores = null;
    if (res && Array.isArray(res.data) && res.data[0] && typeof res.data[0] === "object") {
        scores = res.data[0];
    } else if (res && typeof res.data === "object") {
        scores = res.data;
    } else {
        throw new Error("Unexpected API output shape: " + JSON.stringify(res));
    }
    
    // Parse the scores object into our prediction format
    return Object.entries(scores)
        .map(([label, score]) => {
            const sdgNum = extractSDGNumber(label);
            // Skip invalid SDG numbers
            if (sdgNum === null || sdgNum < 1 || sdgNum > 16) {
                console.warn('Invalid SDG label:', label);
                return null;
            }
            return {
                id: sdgNum,
                label: label,
                score: Number(score),
                rank: 0,
                isManualEdit: false
            };
        })
        .filter(p => p !== null) // Remove invalid entries
        .sort((a, b) => b.score - a.score)
        .slice(0, 3)
        .map((p, i) => ({ ...p, rank: i + 1 }));
}

function renderPredictions(preds) {
    const container = document.getElementById('resultsContainer');
    if (!container) return;
    
    container.innerHTML = preds.map(pred => {
        const sdgNum = extractSDGNumber(pred.label);
        const sdgInfo = SDG_DATA[sdgNum] || { name: pred.label, color: '#666' };
        const pct = (pred.score * 100).toFixed(1);
        const meetsThreshold = pred.score >= CONFIG.MIN_CONFIDENCE_THRESHOLD;
        const isManual = pred.isManualEdit;
        
        let statusClass = '';
        let statusText = '';
        let scoreClass = '';
        let barClass = '';
        let cardClass = 'prediction-card';
        
        if (isManual) {
            cardClass += ' manual-edit';
            statusClass = 'will-save';
            statusText = '✓ Manual edit - will be saved';
            scoreClass = 'manual';
        } else if (meetsThreshold) {
            statusClass = 'will-save';
            statusText = '✓ Meets 75% threshold - will be saved';
        } else {
            cardClass += ' below-threshold';
            statusClass = 'wont-save';
            statusText = '⚠ Below 75% threshold - will not be saved';
            scoreClass = 'below-threshold';
            barClass = 'below-threshold';
        }
        
        return `
            <div class="${cardClass}">
                <div class="prediction-header">
                    <span class="prediction-label">
                        ${isManual ? '✎ ' : ''}SDG ${sdgNum}: ${sdgInfo.name}
                    </span>
                    <span class="prediction-score ${scoreClass}">${pct}%</span>
                </div>
                <div class="prediction-bar">
                    <div class="prediction-bar-fill ${barClass}" style="width: ${pct}%; background: linear-gradient(90deg, ${sdgInfo.color} 0%, ${sdgInfo.color}cc 100%);"></div>
                </div>
                <span class="prediction-status ${statusClass}">${statusText}</span>
            </div>
        `;
    }).join('');
}

async function handleSave() {
    if (!state.currentPreds || state.currentPreds.length === 0) {
        alert('No predictions to save');
        return;
    }
    
    // Collect metadata - department is now optional
    const title = document.getElementById('metaTitle')?.value.trim();
    const author = document.getElementById('metaAuthor')?.value.trim();
    const pubDate = document.getElementById('metaPubDate')?.value;
    const department = document.getElementById('metaDepartment')?.value || 'Other';
    const url = document.getElementById('metaUrl')?.value.trim();
    const abstract = document.getElementById('thesisAbstract')?.value.trim();
    
    if (!title || !author || !pubDate) {
        alert('Please fill in all required metadata fields (Title, Author, Date)');
        expandSection('classifier-section');
        return;
    }
    
    const saveBtn = document.getElementById('save');
    saveBtn.disabled = true;
    saveBtn.innerHTML = `
        <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="20"></circle>
        </svg>
        Saving...
    `;
    
    try {
        const response = await fetch('save_thesis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title,
                author,
                pubDate,
                department,
                url,
                abstract,
                predictions: state.currentPreds
            })
        });
        
        const result = await response.json();
        
        if (result.ok) {
            alert(`Saved successfully! ${result.savedPredictions || 0} SDG tag(s) met the ${CONFIG.MIN_CONFIDENCE_THRESHOLD * 100}% threshold.`);
            
            // Reset form
            document.getElementById('metadataForm')?.reset();
            document.getElementById('predictForm')?.reset();
            document.getElementById('resultsBox')?.classList.add('hidden');
            document.getElementById('emptyMsg')?.classList.remove('hidden');
            state.currentPreds = null;
            state.originalPreds = null;
        } else {
            throw new Error(result.error || 'Save failed');
        }
        
    } catch (err) {
        console.error('Save error:', err);
        alert('Error saving: ' + err.message);
    }
    
    saveBtn.disabled = false;
    saveBtn.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
            <polyline points="17 21 17 13 7 13 7 21"></polyline>
            <polyline points="7 3 7 8 15 8"></polyline>
        </svg>
        Save
    `;
}

async function handleMarkNoRelevant() {
    // Set predictions to empty - thesis saved but no SDG tags
    const title = document.getElementById('metaTitle')?.value.trim();
    const author = document.getElementById('metaAuthor')?.value.trim();
    const pubDate = document.getElementById('metaPubDate')?.value;
    const department = document.getElementById('metaDepartment')?.value;
    const abstract = document.getElementById('thesisAbstract')?.value.trim();
    
    if (!title || !author || !pubDate || !department) {
        alert('Please fill in all required metadata fields first');
        return;
    }
    
    if (!confirm('This will save the thesis without any SDG tags. Continue?')) {
        return;
    }
    
    const btn = document.getElementById('markNoRelevant');
    btn.disabled = true;
    
    try {
        const response = await fetch('save_thesis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title,
                author,
                pubDate,
                department,
                abstract,
                predictions: [],
                noRelevantTags: true
            })
        });
        
        const result = await response.json();
        
        if (result.ok) {
            alert('Thesis saved with no SDG tags.');
            document.getElementById('metadataForm')?.reset();
            document.getElementById('predictForm')?.reset();
            document.getElementById('resultsBox')?.classList.add('hidden');
            document.getElementById('emptyMsg')?.classList.remove('hidden');
            state.currentPreds = null;
        } else {
            throw new Error(result.error || 'Save failed');
        }
        
    } catch (err) {
        console.error('Save error:', err);
        alert('Error: ' + err.message);
    }
    
    btn.disabled = false;
}

// ============================================
// EDIT MODAL
// ============================================
function initModals() {
    // Single entry edit modal
    document.getElementById('closeModal')?.addEventListener('click', closeEditModal);
    document.getElementById('cancelEdits')?.addEventListener('click', closeEditModal);
    document.getElementById('saveEdits')?.addEventListener('click', saveEdits);
    
    // Bulk edit modal
    document.getElementById('closeBulkModal')?.addEventListener('click', closeBulkEditModal);
    document.getElementById('cancelBulkEdits')?.addEventListener('click', closeBulkEditModal);
    document.getElementById('saveBulkEdits')?.addEventListener('click', saveBulkEdits);
    
    // Close on backdrop click
    document.getElementById('editModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'editModal') closeEditModal();
    });
    document.getElementById('bulkEditModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'bulkEditModal') closeBulkEditModal();
    });
}

function openEditModal() {
    const modal = document.getElementById('editModal');
    const editorRows = document.getElementById('editorRows');
    
    if (!modal || !editorRows || !state.currentPreds) return;
    
    editorRows.innerHTML = state.currentPreds.map((pred, i) => {
        const sdgNum = extractSDGNumber(pred.label);
        return `
            <div class="editor-row">
                <label>Prediction ${i + 1}</label>
                ${createSDGSelect(`edit-sdg-${i}`, sdgNum)}
            </div>
        `;
    }).join('');
    
    modal.classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal')?.classList.add('hidden');
}

function saveEdits() {
    if (!state.currentPreds) return;
    
    state.currentPreds = state.currentPreds.map((pred, i) => {
        const select = document.getElementById(`edit-sdg-${i}`);
        if (!select) return pred;
        
        const newSDG = parseInt(select.value);
        const originalSDG = extractSDGNumber(state.originalPreds[i].label);
        
        if (newSDG !== originalSDG) {
            // Manual edit - set to 100%
            return {
                label: `SDG ${newSDG}: ${SDG_DATA[newSDG].name}`,
                score: CONFIG.MANUAL_EDIT_CONFIDENCE,
                rank: pred.rank,
                isManualEdit: true
            };
        }
        
        return pred;
    });
    
    renderPredictions(state.currentPreds);
    closeEditModal();
}

function createSDGSelect(id, selectedValue) {
    const options = Object.entries(SDG_DATA).map(([num, data]) => {
        const selected = parseInt(num) === selectedValue ? 'selected' : '';
        return `<option value="${num}" ${selected}>SDG ${num}: ${data.name}</option>`;
    }).join('');
    
    return `<select id="${id}">${options}</select>`;
}

// ============================================
// BULK IMPORT
// ============================================
function initBulkImport() {
    const uploadForm = document.getElementById('uploadForm');
    const processBtn = document.getElementById('processBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const stopBtn = document.getElementById('stopBtn');
    const selectValidBtn = document.getElementById('selectValidBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const saveAllBtn = document.getElementById('saveAllBtn');
    const downloadResultsBtn = document.getElementById('downloadResultsBtn');
    
    if (uploadForm) {
        uploadForm.addEventListener('submit', handleCSVUpload);
    }
    
    if (processBtn) {
        processBtn.addEventListener('click', processSelectedRecords);
    }
    
    if (pauseBtn) {
        pauseBtn.addEventListener('click', pauseProcessing);
    }
    
    if (stopBtn) {
        stopBtn.addEventListener('click', stopProcessing);
    }
    
    if (selectValidBtn) {
        selectValidBtn.addEventListener('click', selectValidRecords);
    }
    
    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', clearSelection);
    }
    
    if (saveAllBtn) {
        saveAllBtn.addEventListener('click', saveAllResults);
    }
    
    if (downloadResultsBtn) {
        downloadResultsBtn.addEventListener('click', downloadResultsCSV);
    }
}

async function handleCSVUpload(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const file = fileInput?.files[0];
    
    if (!file) {
        alert('Please select a CSV file');
        return;
    }
    
    const status = document.getElementById('uploadStatus');
    status.textContent = 'Reading file...';
    
    try {
        const text = await file.text();
        const parsed = parseCSV(text);
        
        if (parsed.length === 0) {
            throw new Error('No valid records found in CSV');
        }
        
        state.csvData = parsed;
        state.selectedRecords.clear();
        
        renderPreviewTable(parsed);
        
        document.getElementById('bulkStep2')?.classList.remove('hidden');
        const validCount = parsed.filter(r => r.isValid).length;
        status.textContent = `Loaded ${parsed.length} records (${validCount} valid)`;
        
    } catch (err) {
        console.error('CSV parse error:', err);
        status.textContent = 'Error: ' + err.message;
    }
}

function parseCSV(text) {
    const lines = text.split('\n').filter(line => line.trim());
    if (lines.length < 2) return [];
    
    // Parse headers - normalize to lowercase and remove quotes
    const rawHeaders = parseCSVLine(lines[0]);
    const headers = rawHeaders.map(h => h.trim().toLowerCase().replace(/"/g, ''));
    const records = [];
    
    for (let i = 1; i < lines.length; i++) {
        const values = parseCSVLine(lines[i]);
        const rawRecord = {};
        
        headers.forEach((h, idx) => {
            rawRecord[h] = (values[idx] || '').trim();
        });
        
        // Map to expected column names (new format)
        // Required: Last Name, First Name, Year, Title, Abstract
        // Optional: URL
        const lastName = rawRecord['last name'] || rawRecord['lastname'] || rawRecord['last_name'] || '';
        const firstName = rawRecord['first name'] || rawRecord['firstname'] || rawRecord['first_name'] || '';
        const year = rawRecord['year'] || '';
        const title = rawRecord['title'] || '';
        const abstract = rawRecord['abstract'] || '';
        const url = rawRecord['url'] || rawRecord['URL'] || rawRecord['Url'] || rawRecord['link'] || rawRecord['Link'] || rawRecord['LINK'] || '';
        
        // Build validation errors
        const validationErrors = [];
        if (!lastName) validationErrors.push('Missing Last Name');
        if (!firstName) validationErrors.push('Missing First Name');
        if (!year) validationErrors.push('Missing Year');
        if (!title) validationErrors.push('Missing Title');
        if (!abstract) validationErrors.push('Missing Abstract');
        
        // Validate year format
        const yearNum = parseInt(year);
        if (year && (isNaN(yearNum) || yearNum < 1900 || yearNum > 2100)) {
            validationErrors.push('Invalid Year (use YYYY)');
        }
        
        // Create normalized record
        const record = {
            // Core fields
            lastName: lastName,
            firstName: firstName,
            year: year,
            title: title,
            abstract: abstract,
            url: url || null,
            
            // Computed fields for compatibility with save
            author: lastName && firstName ? `${lastName}, ${firstName}` : '',
            publication_date: year ? `${year}-01-01` : '', // Convert YYYY to YYYY-01-01
            
            // Validation
            isValid: validationErrors.length === 0,
            validationErrors: validationErrors,
            index: i - 1
        };
        
        records.push(record);
    }
    
    return records;
}

function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    
    result.push(current.trim());
    return result;
}

function renderPreviewTable(records) {
    const container = document.getElementById('previewTable');
    const summary = document.getElementById('validationSummary');
    
    const validCount = records.filter(r => r.isValid).length;
    const invalidCount = records.length - validCount;
    
    summary.innerHTML = `
        <div class="validation-stats">
            <div class="stat-item">
                <div class="stat-number">${records.length}</div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-item stat-success">
                <div class="stat-number">${validCount}</div>
                <div class="stat-label">Valid</div>
            </div>
            <div class="stat-item stat-error">
                <div class="stat-number">${invalidCount}</div>
                <div class="stat-label">Invalid</div>
            </div>
        </div>
        ${invalidCount > 0 ? `
            <div class="validation-errors-note">
                <strong>Required columns:</strong> Last Name, First Name, Year, Title, Abstract<br>
                <strong>Optional:</strong> URL<br>
                Hover over "Invalid" status to see specific issues for each row.
            </div>
        ` : ''}
    `;
    
    container.innerHTML = `
        <table class="preview-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll" /></th>
                    <th>#</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Year</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${records.map((r, i) => `
                    <tr class="${r.isValid ? '' : 'row-invalid'}">
                        <td>
                            <input type="checkbox" class="record-checkbox" data-index="${i}" 
                                ${r.isValid ? '' : 'disabled'} />
                        </td>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(truncate(r.title || '(no title)', 40))}</td>
                        <td>${escapeHtml(r.author || '(no author)')}</td>
                        <td>${r.year || '(no year)'}</td>
                        <td>
                            ${r.isValid 
                                ? '<span class="status-valid">✓ Valid</span>' 
                                : `<span class="status-invalid" title="${escapeHtml(r.validationErrors.join('; '))}">✗ ${r.validationErrors.join(', ')}</span>`}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    // Add event listeners
    document.getElementById('selectAll')?.addEventListener('change', (e) => {
        document.querySelectorAll('.record-checkbox:not(:disabled)').forEach(cb => {
            cb.checked = e.target.checked;
            const idx = parseInt(cb.dataset.index);
            if (e.target.checked) {
                state.selectedRecords.add(idx);
            } else {
                state.selectedRecords.delete(idx);
            }
        });
        updateSelectionCount();
    });
    
    document.querySelectorAll('.record-checkbox').forEach(cb => {
        cb.addEventListener('change', (e) => {
            const idx = parseInt(e.target.dataset.index);
            if (e.target.checked) {
                state.selectedRecords.add(idx);
            } else {
                state.selectedRecords.delete(idx);
            }
            updateSelectionCount();
        });
    });
}

function selectValidRecords() {
    state.csvData.forEach((r, i) => {
        if (r.isValid) {
            state.selectedRecords.add(i);
            const cb = document.querySelector(`.record-checkbox[data-index="${i}"]`);
            if (cb) cb.checked = true;
        }
    });
    updateSelectionCount();
}

function clearSelection() {
    state.selectedRecords.clear();
    document.querySelectorAll('.record-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateSelectionCount();
}

function updateSelectionCount() {
    const countEl = document.getElementById('selectionCount');
    const processBtn = document.getElementById('processBtn');
    const count = state.selectedRecords.size;
    
    countEl.textContent = `${count} record${count === 1 ? '' : 's'} selected`;
    processBtn.disabled = count === 0;
}

async function processSelectedRecords() {
    if (state.selectedRecords.size === 0) return;
    
    const processBtn = document.getElementById('processBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const status = document.getElementById('processStatus');
    
    // Check for resume data
    const savedProgress = localStorage.getItem('sdg_bulk_progress');
    let startIndex = 0;
    if (savedProgress) {
        const progress = JSON.parse(savedProgress);
        if (confirm(`Found saved progress (${progress.processed}/${progress.total} records). Resume where you left off?`)) {
            startIndex = progress.processed;
            state.processedResults = progress.results || [];
        } else {
            localStorage.removeItem('sdg_bulk_progress');
            state.processedResults = [];
        }
    } else {
        state.processedResults = [];
    }
    
    processBtn.disabled = true;
    if (pauseBtn) {
        pauseBtn.classList.remove('hidden');
        pauseBtn.disabled = false;
    }
    const stopBtn = document.getElementById('stopBtn');
    if (stopBtn) {
        stopBtn.classList.remove('hidden');
    }
    state.isPaused = false;
    state.shouldStop = false;
    
    const indices = Array.from(state.selectedRecords);
    const total = indices.length;
    let processed = startIndex;
    const startTime = Date.now();
    
    // Connect to Gradio once for all requests
    let app = null;
    try {
        status.innerHTML = 'Connecting to AI model...';
        app = await hfClient(
            `${CONFIG.SPACE_OWNER}/${CONFIG.SPACE_NAME}`,
            CONFIG.HF_TOKEN ? { hf_token: CONFIG.HF_TOKEN } : undefined
        );
    } catch (err) {
        status.innerHTML = `<span class="status-invalid">Failed to connect to AI model: ${err.message}</span>`;
        processBtn.disabled = false;
        if (pauseBtn) pauseBtn.classList.add('hidden');
        return;
    }
    
    for (let i = startIndex; i < indices.length; i++) {
        // Check for pause
        if (state.isPaused || state.shouldStop) {
            // Save progress
            localStorage.setItem('sdg_bulk_progress', JSON.stringify({
                processed: i,
                total: total,
                results: state.processedResults,
                timestamp: Date.now()
            }));
            
            if (state.shouldStop) {
                status.innerHTML = `<span class="status-warning">⏹ Stopped at ${i}/${total}. Progress saved - you can resume later.</span>`;
            } else {
                status.innerHTML = `<span class="status-warning">⏸ Paused at ${i}/${total}. Progress saved - click Resume to continue.</span>`;
            }
            
            processBtn.disabled = false;
            processBtn.textContent = 'Resume Processing';
            if (pauseBtn) pauseBtn.classList.add('hidden');
            if (stopBtn) stopBtn.classList.add('hidden');
            renderBulkResults();
            return;
        }
        
        const idx = indices[i];
        const record = state.csvData[idx];
        processed++;
        
        // Calculate time estimates
        const elapsed = (Date.now() - startTime) / 1000;
        const avgTimePerRecord = elapsed / (processed - startIndex);
        const remaining = (total - processed) * avgTimePerRecord;
        const remainingMin = Math.floor(remaining / 60);
        const remainingSec = Math.floor(remaining % 60);
        
        status.innerHTML = `
            <div style="margin-bottom: 8px;">
                <strong>Processing ${processed}/${total}</strong> (${Math.round(processed/total*100)}%)
            </div>
            <div style="font-size: 0.85rem; color: #666;">
                ${truncate(record.title, 50)}<br>
                Est. remaining: ${remainingMin}m ${remainingSec}s
            </div>
            <div style="background: #e0e0e0; border-radius: 4px; height: 8px; margin-top: 8px;">
                <div style="background: #4CAF50; height: 100%; border-radius: 4px; width: ${(processed/total*100)}%; transition: width 0.3s;"></div>
            </div>
        `;
        
        try {
            const res = await app.predict("/predict", { text: record.abstract });
            
            let scores = null;
            if (res && Array.isArray(res.data) && res.data[0] && typeof res.data[0] === "object") {
                scores = res.data[0];
            } else if (res && typeof res.data === "object") {
                scores = res.data;
            } else {
                throw new Error("Unexpected API response format");
            }
            
            const preds = Object.entries(scores)
                .map(([label, score]) => {
                    const sdgNum = extractSDGNumber(label);
                    if (sdgNum === null || sdgNum < 1 || sdgNum > 16) {
                        return null;
                    }
                    return {
                        id: sdgNum,
                        label: label,
                        score: Number(score),
                        rank: 0,
                        isManualEdit: false
                    };
                })
                .filter(p => p !== null)
                .sort((a, b) => b.score - a.score)
                .slice(0, 3)
                .map((p, i) => ({ ...p, rank: i + 1 }));
            
            const meetsThreshold = preds.some(p => p.score >= CONFIG.MIN_CONFIDENCE_THRESHOLD);
            
            state.processedResults.push({
                record,
                predictions: preds,
                status: meetsThreshold ? 'success' : 'low_confidence',
                error: null
            });
            
        } catch (err) {
            console.error(`Error processing record ${idx}:`, err);
            state.processedResults.push({
                record,
                predictions: [],
                status: 'error',
                error: err.message || String(err)
            });
        }
        
        // Save progress periodically (every 25 records)
        if (processed % 25 === 0) {
            localStorage.setItem('sdg_bulk_progress', JSON.stringify({
                processed: i + 1,
                total: total,
                results: state.processedResults,
                timestamp: Date.now()
            }));
        }
        
        // Rate limiting delay (reduced for faster processing)
        if (i < indices.length - 1) {
            await new Promise(r => setTimeout(r, 400));
        }
    }
    
    // Clear saved progress on completion
    localStorage.removeItem('sdg_bulk_progress');
    
    const totalTime = Math.round((Date.now() - startTime) / 1000);
    const minutes = Math.floor(totalTime / 60);
    const seconds = totalTime % 60;
    
    status.innerHTML = `<span class="status-valid">✓ Processed ${processed} records in ${minutes}m ${seconds}s</span>`;
    renderBulkResults();
    
    document.getElementById('bulkStep3')?.classList.remove('hidden');
    processBtn.disabled = false;
    processBtn.textContent = 'Process Selected Records';
    if (pauseBtn) pauseBtn.classList.add('hidden');
    const stopBtnEnd = document.getElementById('stopBtn');
    if (stopBtnEnd) stopBtnEnd.classList.add('hidden');
}

function pauseProcessing() {
    state.isPaused = true;
}

function stopProcessing() {
    state.shouldStop = true;
    state.isPaused = true;
}

function clearSavedProgress() {
    if (confirm('Are you sure you want to clear saved progress? This cannot be undone.')) {
        localStorage.removeItem('sdg_bulk_progress');
        alert('Saved progress cleared.');
    }
}

function renderBulkResults() {
    const container = document.getElementById('bulkResultsTable');
    const saveAllBtn = document.getElementById('saveAllBtn');
    const downloadBtn = document.getElementById('downloadResultsBtn');
    
    const successCount = state.processedResults.filter(r => 
        r.status === 'success' || r.predictions.some(p => p.isManualEdit)
    ).length;
    const errorCount = state.processedResults.filter(r => r.status === 'error').length;
    const lowConfCount = state.processedResults.filter(r => r.status === 'low_confidence').length;
    
    container.innerHTML = `
        <div class="bulk-results-summary">
            <p><strong>${successCount}</strong> ready to save (≥75% or manually edited)</p>
            <p><strong>${lowConfCount}</strong> below threshold (can edit to save)</p>
            <p><strong>${errorCount}</strong> errors</p>
            <button class="btn-secondary" onclick="resetBulkImport()" style="margin-top: 1rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                    <path d="M3 3v5h5"></path>
                </svg>
                Start Over
            </button>
        </div>
        <table class="results-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Year</th>
                    <th>SDG 1</th>
                    <th>SDG 2</th>
                    <th>SDG 3</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${state.processedResults.map((r, i) => {
                    const preds = r.predictions || [];
                    return `
                        <tr class="${r.status === 'error' ? 'row-invalid' : ''}">
                            <td>${i + 1}</td>
                            <td>${escapeHtml(truncate(r.record.title, 30))}</td>
                            <td>${escapeHtml(truncate(r.record.author, 20))}</td>
                            <td>${r.record.year || '-'}</td>
                            ${[0, 1, 2].map(j => {
                                const p = preds[j];
                                if (!p) return '<td>-</td>';
                                const sdgNum = p.id || extractSDGNumber(p.label);
                                const pct = (p.score * 100).toFixed(0);
                                const isHigh = p.score >= CONFIG.MIN_CONFIDENCE_THRESHOLD || p.isManualEdit;
                                return `<td class="${isHigh ? 'high-confidence' : 'low-confidence'}">
                                    ${p.isManualEdit ? '✎ ' : ''}SDG ${sdgNum} (${pct}%)
                                </td>`;
                            }).join('')}
                            <td>
                                ${r.status === 'error' 
                                    ? `<span class="status-invalid" title="${escapeHtml(r.error || 'Unknown error')}">✗ Error</span>`
                                    : r.status === 'success' || preds.some(p => p.isManualEdit)
                                        ? '<span class="status-valid">✓ Ready</span>'
                                        : '<span class="status-warning">⚠ Low Conf.</span>'}
                            </td>
                            <td>
                                ${r.status === 'error' 
                                    ? `<span class="error-detail" title="${escapeHtml(r.error || 'Unknown')}">View Error</span>`
                                    : `<button class="edit-tags-btn" onclick="openBulkEditModal(${i})">Edit Tags</button>`}
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
    
    saveAllBtn.disabled = successCount === 0;
    downloadBtn.disabled = state.processedResults.length === 0;
}

// Reset bulk import to start over
window.resetBulkImport = function() {
    state.csvData = [];
    state.selectedRecords.clear();
    state.processedResults = [];
    
    document.getElementById('bulkStep2')?.classList.add('hidden');
    document.getElementById('bulkStep3')?.classList.add('hidden');
    document.getElementById('uploadForm')?.reset();
    document.getElementById('uploadStatus').textContent = '';
    document.getElementById('processStatus').textContent = '';
    document.getElementById('saveStatus').textContent = '';
};

// Bulk edit modal
window.openBulkEditModal = function(index) {
    state.editingBulkIndex = index;
    const result = state.processedResults[index];
    
    const modal = document.getElementById('bulkEditModal');
    const title = document.getElementById('bulkEditTitle');
    const editorRows = document.getElementById('bulkEditorRows');
    
    title.textContent = `Edit Tags: ${truncate(result.record.title, 40)}`;
    
    const preds = result.predictions || [];
    
    editorRows.innerHTML = [0, 1, 2].map(i => {
        const pred = preds[i];
        const sdgNum = pred ? extractSDGNumber(pred.label) : 1;
        return `
            <div class="editor-row">
                <label>SDG Tag ${i + 1}</label>
                ${createSDGSelect(`bulk-edit-sdg-${i}`, sdgNum)}
            </div>
        `;
    }).join('');
    
    modal.classList.remove('hidden');
};

function closeBulkEditModal() {
    document.getElementById('bulkEditModal')?.classList.add('hidden');
    state.editingBulkIndex = null;
}

function saveBulkEdits() {
    if (state.editingBulkIndex === null) return;
    
    const result = state.processedResults[state.editingBulkIndex];
    const originalPreds = result.predictions || [];
    
    const newPreds = [0, 1, 2].map(i => {
        const select = document.getElementById(`bulk-edit-sdg-${i}`);
        const newSDG = parseInt(select.value);
        const originalSDG = originalPreds[i] ? extractSDGNumber(originalPreds[i].label) : null;
        
        // If changed or was empty, mark as manual edit with 100%
        if (!originalSDG || newSDG !== originalSDG) {
            return {
                label: `SDG ${newSDG}: ${SDG_DATA[newSDG].name}`,
                score: CONFIG.MANUAL_EDIT_CONFIDENCE,
                rank: i + 1,
                isManualEdit: true
            };
        }
        
        return originalPreds[i];
    });
    
    result.predictions = newPreds;
    result.status = 'success'; // Manual edits make it saveable
    
    renderBulkResults();
    closeBulkEditModal();
}

async function saveAllResults() {
    const saveBtn = document.getElementById('saveAllBtn');
    const status = document.getElementById('saveStatus');
    
    saveBtn.disabled = true;
    
    // Filter to only save records that meet threshold or have manual edits
    const toSave = state.processedResults.filter(r => 
        r.status === 'success' || r.predictions.some(p => p.isManualEdit)
    );
    
    if (toSave.length === 0) {
        status.textContent = 'No records meet the threshold. Edit tags to save.';
        saveBtn.disabled = false;
        return;
    }
    
    let saved = 0;
    let errors = 0;
    let errorMessages = [];
    
    for (let i = 0; i < toSave.length; i++) {
        const result = toSave[i];
        status.textContent = `Saving ${i + 1}/${toSave.length}...`;
        
        try {
            const record = result.record;
            
            // Deduplicate predictions by SDG number
            const seen = new Set();
            const dedupedPredictions = result.predictions
                .map(p => {
                    let sdgNum = p.id;
                    if (!sdgNum || sdgNum < 1 || sdgNum > 16) {
                        const match = (p.label || '').match(/(\d+)/);
                        sdgNum = match ? parseInt(match[1]) : null;
                    }
                    if (!sdgNum || sdgNum < 1 || sdgNum > 16) return null;
                    if (seen.has(sdgNum)) return null;
                    seen.add(sdgNum);
                    return {
                        id: sdgNum,
                        label: p.label || `SDG ${sdgNum}`,
                        score: parseFloat(p.score) || 0,
                        isManualEdit: p.isManualEdit || false
                    };
                })
                .filter(p => p !== null);
            
            const payload = {
                title: record.title,
                author: record.author,
                pubDate: record.publication_date,
                department: 'Other',
                url: record.url || '',
                abstract: record.abstract,
                predictions: dedupedPredictions
            };
            
            // Log what we're sending (for debugging)
            console.log(`Saving record ${i + 1}:`, {
                title: payload.title.substring(0, 50),
                author: payload.author,
                predictions: payload.predictions.map(p => `SDG ${p.id} (${(p.score*100).toFixed(0)}%)`)
            });
            
            const response = await fetch('save_thesis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const responseText = await response.text();
            console.log(`Response for record ${i + 1}:`, responseText.substring(0, 500));
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}`);
            }
            
            if (data.ok) {
                saved++;
                result.savedStatus = 'saved';
                result.savedId = data.thesisId || data.pendingId;
                console.log(`Record ${i + 1} saved with ID: ${result.savedId}`);
            } else if (data.duplicate) {
                result.savedStatus = 'skipped';
                result.saveError = `Duplicate: Already exists as ID ${data.existingId}`;
                errorMessages.push(`Row ${i + 1}: Duplicate entry (existing ID: ${data.existingId})`);
                errors++;
            } else {
                errors++;
                result.savedStatus = 'error';
                result.saveError = data.error || 'Unknown error';
                errorMessages.push(`Row ${i + 1}: ${data.error}`);
                console.error(`Record ${i + 1} error:`, data.error);
            }
            
        } catch (err) {
            errors++;
            result.savedStatus = 'error';
            result.saveError = err.message;
            errorMessages.push(`Row ${i + 1}: ${err.message}`);
            console.error(`Record ${i + 1} exception:`, err);
        }
        
        await new Promise(r => setTimeout(r, 100));
    }
    
    // Update status with summary
    if (errors === 0) {
        status.innerHTML = `<span class="status-valid">✓ Successfully saved all ${saved} records!</span>`;
    } else {
        status.innerHTML = `
            <span class="status-warning">Saved ${saved} of ${toSave.length} records (${errors} errors)</span>
            <button class="btn-link" onclick="showSaveErrors()">View Errors</button>
        `;
        state.lastSaveErrors = errorMessages;
    }
    
    saveBtn.disabled = false;
}

// Show save errors in an alert or modal
window.showSaveErrors = function() {
    if (state.lastSaveErrors && state.lastSaveErrors.length > 0) {
        alert('Save Errors:\n\n' + state.lastSaveErrors.join('\n\n'));
    }
};

function downloadResultsCSV() {
    const rows = [['Title', 'Author', 'Date', 'SDG1', 'Score1', 'SDG2', 'Score2', 'SDG3', 'Score3', 'Status', 'Meets_Threshold', 'Has_Manual_Edit']];
    
    state.processedResults.forEach(r => {
        const preds = r.predictions || [];
        const author = r.record.author1_fname 
            ? `${r.record.author1_fname} ${r.record.author1_lname || ''}`
            : r.record.author;
        
        const meetsThreshold = preds.some(p => p.score >= CONFIG.MIN_CONFIDENCE_THRESHOLD);
        const hasManualEdit = preds.some(p => p.isManualEdit);
        
        rows.push([
            `"${(r.record.title || '').replace(/"/g, '""')}"`,
            `"${(author || '').replace(/"/g, '""')}"`,
            r.record.publication_date || '',
            preds[0] ? extractSDGNumber(preds[0].label) : '',
            preds[0] ? (preds[0].score * 100).toFixed(1) + '%' : '',
            preds[1] ? extractSDGNumber(preds[1].label) : '',
            preds[1] ? (preds[1].score * 100).toFixed(1) + '%' : '',
            preds[2] ? extractSDGNumber(preds[2].label) : '',
            preds[2] ? (preds[2].score * 100).toFixed(1) + '%' : '',
            r.status,
            meetsThreshold ? 'YES' : 'NO',
            hasManualEdit ? 'YES' : 'NO'
        ]);
    });
    
    // Add summary rows at the bottom
    const totalCount = state.processedResults.length;
    const successCount = state.processedResults.filter(r => r.status === 'success').length;
    const lowConfCount = state.processedResults.filter(r => r.status === 'low_confidence').length;
    const errorCount = state.processedResults.filter(r => r.status === 'error').length;
    const manualEditCount = state.processedResults.filter(r => r.predictions?.some(p => p.isManualEdit)).length;
    
    rows.push([]);
    rows.push(['SUMMARY']);
    rows.push(['Total Processed', totalCount]);
    rows.push(['Met 75% Threshold', successCount]);
    rows.push(['Below Threshold', lowConfCount]);
    rows.push(['Errors', errorCount]);
    rows.push(['Manually Edited', manualEditCount]);
    rows.push(['Threshold Used', (CONFIG.MIN_CONFIDENCE_THRESHOLD * 100) + '%']);
    
    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `sdg_classification_results_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    
    URL.revokeObjectURL(url);
}

// ============================================
// LOGIN
// ============================================
function initLogin() {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
}

async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('loginUsername')?.value.trim();
    const password = document.getElementById('loginPassword')?.value;
    const errorEl = document.getElementById('loginError');
    
    if (!username || !password) {
        errorEl.textContent = 'Please enter username and password';
        errorEl.classList.remove('hidden');
        return;
    }
    
    errorEl.classList.add('hidden');
    
    try {
        const response = await fetch('login_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reload page to show authenticated state
            window.location.reload();
        } else {
            errorEl.textContent = data.error || 'Login failed';
            errorEl.classList.remove('hidden');
        }
        
    } catch (err) {
        errorEl.textContent = 'Error connecting to server';
        errorEl.classList.remove('hidden');
    }
}

// ============================================
// UTILITIES
// ============================================
function extractSDGNumber(label) {
    if (!label) return null;
    // Try to match "SDG X" pattern first (like "SDG 1: No Poverty")
    const sdgMatch = label.match(/^SDG\s+(\d+)/i);
    if (sdgMatch) {
        return parseInt(sdgMatch[1]);
    }
    // Fallback to any number in the string
    const numMatch = label.match(/(\d+)/);
    return numMatch ? parseInt(numMatch[1]) : null;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncate(text, maxLen) {
    if (!text) return '';
    return text.length > maxLen ? text.slice(0, maxLen) + '...' : text;
}
