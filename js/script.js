document.addEventListener('DOMContentLoaded', () => {
    console.log("[Init] DOMContentLoaded disparado. Reorganizado para evitar erros de inicialização.");

    // --- Constantes Globais ---
    const DEFAULT_NEW_NOTE_TITLE = "Nova Nota";
    const MAX_AUTO_TITLE_LENGTH = 50;

    // --- Seletores Globais ---
    const tabLinks = document.querySelectorAll('.tab-nav .tab-link');
    const tabContents = document.querySelectorAll('.tab-content-wrapper .tab-content');
    const mainHeader = document.querySelector('.main-header');

    let categoryNav, categoryTabsContainer, addCategoryBtn, notesTabNav, notesContentAreaForMednotes,
        addNoteTabBtn, placeholder, stickyNotesNavBarsForMednotes;

    function initializeMedNotesSelectors() {
        const mednotesTab = document.getElementById('mednotes');
        if (mednotesTab) {
            categoryNav = mednotesTab.querySelector('.category-nav');
            categoryTabsContainer = mednotesTab.querySelector('.category-tabs-container');
            addCategoryBtn = mednotesTab.querySelector('#add-category-btn');
            notesTabNav = mednotesTab.querySelector('.notes-tab-nav');
            notesContentAreaForMednotes = mednotesTab.querySelector('.notes-content-area');
            addNoteTabBtn = mednotesTab.querySelector('#add-note-tab-btn');
            placeholder = mednotesTab.querySelector('.note-editor-placeholder');
            stickyNotesNavBarsForMednotes = mednotesTab.querySelector('.sticky-notes-nav-bars');
        }
    }

    // --- Estado Global ---
    let categories = {};
    let notes = {};
    let activeCategoryId = null;
    let activeNoteId = null;
    let saveTimeout = null;
    let isSaving = false;
    let dataLoaded = false;
    let draggedCategory = null;
    let draggedNoteTab = null;

    // =====================================================
    // --- DECLARAÇÃO DE TODAS AS FUNÇÕES (MedNotes) PRIMEIRO ---
    // =====================================================

    function updateAddCategoryButtonAttention() {
        const currentAddCategoryBtn = document.getElementById('add-category-btn');
        if (!currentAddCategoryBtn) return;
        const hasRealCategories = Object.values(categories).some(cat => cat && !cat.isTemporary && cat.id && !cat.id.startsWith('new-cat-'));
        if (!hasRealCategories) {
            currentAddCategoryBtn.classList.add('needs-attention');
        } else {
            currentAddCategoryBtn.classList.remove('needs-attention');
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => { clearTimeout(timeout); func(...args); };
            clearTimeout(timeout); timeout = setTimeout(later, wait);
        };
    }

    const adjustStickyLayout = () => {
    const mednotesTab = document.getElementById('mednotes');
    if (!mednotesTab || !mednotesTab.classList.contains('active')) return; // Só ajusta se a aba mednotes estiver ativa

    // Certifique-se que mainHeader e stickyNotesNavBarsForMednotes estão definidos
    if (!mainHeader) {
        console.error("adjustStickyLayout: mainHeader não definido");
        return;
    }
    if (!stickyNotesNavBarsForMednotes) {
        console.warn("adjustStickyLayout: stickyNotesNavBarsForMednotes não definido (pode ser ok se a aba não for mednotes)");
        // Se estiver na aba mednotes, isso seria um erro.
        if (mednotesTab && mednotesTab.classList.contains('active')) {
            console.error("adjustStickyLayout: ERRO - stickyNotesNavBarsForMednotes não definido na aba mednotes ativa");
        }
        return; // Não continue se os elementos não estiverem prontos para a aba mednotes
    }


    const mainHeaderHeight = mainHeader.offsetHeight;
    stickyNotesNavBarsForMednotes.style.top = mainHeaderHeight + 'px';

    const stickyNavBarsHeight = stickyNotesNavBarsForMednotes.offsetHeight;
    const totalStickyHeight = mainHeaderHeight + stickyNavBarsHeight;

    console.log("Main Header Height:", mainHeaderHeight);
    console.log("Sticky Nav Bars Height:", stickyNavBarsHeight);
    console.log("Total Sticky Height for scroll-padding:", totalStickyHeight);

    document.documentElement.style.scrollPaddingTop = totalStickyHeight + 'px';

    // A lógica de autoGrowTextarea dentro do requestAnimationFrame aqui pode ser problemática.
    // Ela deve ser chamada principalmente no evento 'input' do textarea.
    // Se precisar reajustar a altura de todos os textareas visíveis após um resize,
    // faça isso explicitamente, mas não no contexto de manter o cursor visível.
    requestAnimationFrame(() => {
        if (activeNoteId && notes[activeNoteId] && notes[activeNoteId].editorElement &&
            notes[activeNoteId].editorElement.offsetParent !== null) {
            // Apenas reajuste a altura se necessário, não force scroll.
            // autoGrowTextarea(notes[activeNoteId].editorElement); // Cuidado com loops ou comportamento inesperado
        }
    });
};

    const autoGrowTextarea = (element) => {
    if (!element) return;
    element.style.height = 'auto'; // Redefine para o navegador calcular o scrollHeight correto
    element.style.height = (element.scrollHeight) + 'px'; // Adicione uma pequena folga se necessário, ex: +2
};


    const showStatus = (message, type = 'info', duration = 3000) => {
        const noteStatusElement = document.getElementById('note-status');
        if (!noteStatusElement) { console.log(`[Status] ${type.toUpperCase()}: ${message}`); if (type === 'error') console.error(`[Status] ERROR: ${message}`); return; }
        if (noteStatusElement.timeoutId) clearTimeout(noteStatusElement.timeoutId);
        noteStatusElement.textContent = message;
        noteStatusElement.className = `status-message bar-status ${type}`;
        if (!noteStatusElement.hasAttribute('aria-live')) noteStatusElement.setAttribute('aria-live', 'polite');
        if (type !== 'saving' && duration > 0) {
             noteStatusElement.timeoutId = setTimeout(() => {
                if (noteStatusElement.textContent === message) { noteStatusElement.textContent = ''; noteStatusElement.className = 'status-message bar-status'; }
                noteStatusElement.timeoutId = null;
             }, duration);
        } else { noteStatusElement.timeoutId = null; }
    };

    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') { try { unsafe = String(unsafe); } catch (e) { return ''; } }
        return unsafe.replace(/&/g, "&amp;")
                     .replace(/</g, "&lt;")
                     .replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;")
                     .replace(/'/g, "&#039;");
    }

    const updatePlaceholderVisibility = () => {
        if (!placeholder) { initializeMedNotesSelectors(); if(!placeholder) return; }
        const hasActiveNote = activeNoteId !== null && notes[activeNoteId];
        placeholder.style.display = hasActiveNote ? 'none' : 'flex';
    };

    const saveAppState = () => {
        if (!notesTabNav || !categoryTabsContainer) { initializeMedNotesSelectors(); if (!notesTabNav || !categoryTabsContainer) return; }
        const tabOrderByCategory = {};
        let previouslySavedOrders = {};
        try { previouslySavedOrders = JSON.parse(localStorage.getItem('mednotesTabOrderByCategory') || '{}'); }
        catch(e) { console.error("[State] Erro ao ler ordens salvas:", e); }
        for (const categoryId in categories) {
            if (categories.hasOwnProperty(categoryId) && !categoryId.startsWith('new-cat-')) {
                if (categoryId === activeCategoryId) {
                    const currentCategoryTabs = notesTabNav.querySelectorAll(`.note-tab-button[data-note-id]`);
                    tabOrderByCategory[categoryId] = Array.from(currentCategoryTabs).map(b => b.dataset.noteId).filter(id => id && !id.startsWith('new-'));
                } else { tabOrderByCategory[categoryId] = previouslySavedOrders[categoryId] || []; }
            }
        }
        try {
            localStorage.setItem('mednotesTabOrderByCategory', JSON.stringify(tabOrderByCategory));
            if (activeCategoryId && !activeCategoryId.startsWith('new-cat-')) localStorage.setItem('mednotesLastActiveCategoryId', activeCategoryId); else localStorage.removeItem('mednotesLastActiveCategoryId');
            if (activeNoteId && !activeNoteId.startsWith('new-')) localStorage.setItem('mednotesLastActiveNoteId', activeNoteId); else localStorage.removeItem('mednotesLastActiveNoteId');
        } catch (e) { console.error('[State] Erro ao salvar estado:', e); showStatus('Erro ao salvar preferências.', 'error', 5000); }
    };

    const renderCategoryTabs = () => {
        if (!categoryTabsContainer || !addCategoryBtn) { initializeMedNotesSelectors(); if (!categoryTabsContainer || !addCategoryBtn) return; }
        categoryTabsContainer.querySelectorAll('.category-tab-button[data-category-id]').forEach(btn => btn.remove());
        let displayOrderIds = [];
        const allCurrentCategoryIds = Object.keys(categories).filter(id => categories[id] && !categories[id].isTemporary);
        try {
            const storedOrderJson = localStorage.getItem('mednotesCategoryOrder');
            if (storedOrderJson) {
                const storedOrder = JSON.parse(storedOrderJson);
                storedOrder.forEach(id => { if (allCurrentCategoryIds.includes(id)) displayOrderIds.push(id); });
                const newCategories = allCurrentCategoryIds.filter(id => !displayOrderIds.includes(id)).sort((a,b) => (categories[a]?.order ?? Infinity) - (categories[b]?.order ?? Infinity));
                displayOrderIds.push(...newCategories);
            } else { displayOrderIds = [...allCurrentCategoryIds].sort((a,b) => (categories[a]?.order ?? Infinity) - (categories[b]?.order ?? Infinity)); }
        } catch (e) { console.error("[RenderCat] Erro ordem:", e); displayOrderIds = [...allCurrentCategoryIds].sort((a,b) => (categories[a]?.order ?? Infinity) - (categories[b]?.order ?? Infinity)); }
        displayOrderIds.forEach(catId => { if (categories[catId]) { const button = createCategoryTabButton(catId, categories[catId].name); if (catId === activeCategoryId && button) button.classList.add('active-category-tab'); }});
        requestAnimationFrame(adjustStickyLayout);
        updateAddCategoryButtonAttention();
    };

    const createCategoryTabButton = (catId, catName) => {
        if (!categoryTabsContainer || !addCategoryBtn) { initializeMedNotesSelectors(); if (!categoryTabsContainer || !addCategoryBtn) return null; }
        const catButton = document.createElement('button'); catButton.className = 'category-tab-button'; catButton.dataset.categoryId = catId; catButton.draggable = true;
        const nameContainer = document.createElement('div'); nameContainer.className = 'category-name-container'; nameContainer.title = `Categoria: ${escapeHtml(catName)}`;
        const nameSpan = document.createElement('span'); nameSpan.className = 'category-name-span'; nameSpan.textContent = catName; nameContainer.appendChild(nameSpan);
        nameContainer.addEventListener('dblclick', (e) => { e.stopPropagation(); handleCategoryNameEdit(catButton.dataset.categoryId, catButton, nameContainer, nameSpan); });
        const deleteBtn = document.createElement('button'); deleteBtn.className = 'category-action-button delete-cat-btn'; deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>'; deleteBtn.title = 'Excluir Categoria'; deleteBtn.setAttribute('aria-label', 'Excluir Categoria');
        deleteBtn.addEventListener('click', (e) => { e.stopPropagation(); const cId = catButton.dataset.categoryId; if (cId.startsWith('new-cat-')) {catButton.remove(); delete categories[cId]; updateAddCategoryButtonAttention();} else deleteCategory(cId); });
        catButton.appendChild(nameContainer); catButton.appendChild(deleteBtn);
        catButton.addEventListener('click', (e) => { if (e.target.closest('.category-action-button') || catButton.querySelector('.edit-category-name-input')) return; const cId = catButton.dataset.categoryId; if (!cId.startsWith('new-cat-') && activeCategoryId !== cId) selectCategory(cId); else if (cId.startsWith('new-cat-')) { const input = catButton.querySelector('.edit-category-name-input'); if(input) input.focus(); }});
        catButton.addEventListener('dragstart', (e) => { if (catButton.querySelector('.edit-category-name-input')) { e.preventDefault(); return; } draggedCategory = catButton; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', catId); } catch(err) { e.dataTransfer.setData('text', catId); } setTimeout(() => { if(draggedCategory === catButton) catButton.style.opacity = '0.5'; }, 0); });
        catButton.addEventListener('dragend', () => { if (draggedCategory === catButton) catButton.style.opacity = '1'; if (draggedCategory === catButton) draggedCategory = null; if(categoryTabsContainer) categoryTabsContainer.querySelectorAll('.drag-over-placeholder-cat').forEach(el => el.remove()); });
        categoryTabsContainer.insertBefore(catButton, addCategoryBtn);
        return catButton;
    };

    const renderNoteTabsForActiveCategory = () => {
        if (!notesTabNav || !addNoteTabBtn) { initializeMedNotesSelectors(); if (!notesTabNav || !addNoteTabBtn) return; }
        notesTabNav.querySelectorAll('.note-tab-button[data-note-id]').forEach(btn => btn.remove());
        if (!activeCategoryId) { requestAnimationFrame(adjustStickyLayout); return; }
        let noteOrder = []; try { const allO = JSON.parse(localStorage.getItem('mednotesTabOrderByCategory') || '{}'); noteOrder = allO[activeCategoryId] || []; } catch (e) {}
        const notesInCategory = Object.entries(notes).filter(([nId, note]) => note.categoryId === activeCategoryId && !nId.startsWith('new-'));
        const notesMap = new Map(notesInCategory);
        noteOrder.forEach(noteId => { const nIdStr = noteId.toString(); if (notesMap.has(nIdStr)) { createNoteTab(nIdStr, notesMap.get(nIdStr).title); notesMap.delete(nIdStr); }});
        notesMap.forEach((noteData, noteId) => createNoteTab(noteId, noteData.title));
        if (activeNoteId && notes[activeNoteId]?.categoryId === activeCategoryId) { const aTab = notesTabNav.querySelector(`.note-tab-button[data-note-id="${activeNoteId}"]`); if (aTab) aTab.classList.add('active-note-tab'); }
        else if (activeNoteId && notes[activeNoteId]?.categoryId !== activeCategoryId) activeNoteId = null;
        requestAnimationFrame(adjustStickyLayout);
    };

    const createEditor = (noteId) => {
        if (!notesContentAreaForMednotes) { initializeMedNotesSelectors(); if (!notesContentAreaForMednotes) {console.error("[Editor] Area de conteúdo MedNotes não encontrada."); return null;} }
        if (!notes[noteId]) { console.error(`[Editor] Nota ${noteId} não encontrada no estado.`); return null; }

        let editor = notesContentAreaForMednotes.querySelector(`#editor-${noteId}`);
        if (editor) { if (editor.value !== (notes[noteId].content || '')) editor.value = notes[noteId].content || ''; autoGrowTextarea(editor); return editor; }
        editor = document.createElement('textarea'); editor.id = `editor-${noteId}`; editor.classList.add('note-editor'); editor.placeholder = 'Comece a digitar sua nota aqui...'; editor.value = notes[noteId].content || ''; editor.style.display = 'none';
        // Dentro de createEditor, no event listener de 'input'
editor.addEventListener('input', (e) => {
    const currentEditor = e.target;
    const editorNoteId = currentEditor.id.replace('editor-', '');

    // Salva a posição do scroll do HTML ANTES do autoGrow
    const scrollContainer = document.documentElement;
    let scrollTopBeforeGrow = scrollContainer.scrollTop;
    // Poderia também salvar a posição relativa do cursor, mas vamos tentar mais simples primeiro
    // let cursorLineBefore = currentEditor.value.substr(0, currentEditor.selectionStart).split('\n').length;

    autoGrowTextarea(currentEditor); // Deixa o textarea crescer

    // Lógica de salvar conteúdo e título (como você já tem)
    if (activeNoteId === editorNoteId && notes[editorNoteId]) {
        const currentContentInEditor = currentEditor.value;
        if (notes[editorNoteId].content !== currentContentInEditor) {
            notes[editorNoteId].content = currentContentInEditor;
            if (notes[editorNoteId].titleIsAutomatic) {
                const firstLine = (currentContentInEditor.split('\n')[0] || "").trim();
                let newProposedTitle = firstLine ? firstLine.substring(0, MAX_AUTO_TITLE_LENGTH) : DEFAULT_NEW_NOTE_TITLE;
                if (!newProposedTitle && notes[editorNoteId].title !== DEFAULT_NEW_NOTE_TITLE) newProposedTitle = DEFAULT_NEW_NOTE_TITLE;
                if (notes[editorNoteId].title !== newProposedTitle) {
                    notes[editorNoteId].title = newProposedTitle;
                    if(notesTabNav) {
                        const noteTabButton = notesTabNav.querySelector(`.note-tab-button[data-note-id="${editorNoteId}"]`);
                        if (noteTabButton) {
                            const titleSpan = noteTabButton.querySelector('.note-title-span');
                            const titleContainer = noteTabButton.querySelector('.note-title-container');
                            if (titleSpan) titleSpan.textContent = newProposedTitle;
                            if (titleContainer) titleContainer.title = newProposedTitle;
                        }
                    }
                    if (notes[editorNoteId].saved === true) notes[editorNoteId].saved = false;
                }
            }
            if (notes[editorNoteId].saved === true) {
                notes[editorNoteId].saved = false;
                showStatus('Alterações não salvas...', 'info', 5000);
            }
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                if (!isSaving && activeNoteId === editorNoteId && notes[editorNoteId]?.saved === false) {
                    saveNote(editorNoteId, true);
                }
            }, 1000);
        }
    }

    // Após autoGrow, o navegador pode ter saltado.
    // Tenta restaurar a posição de scroll que tínhamos ANTES do textarea crescer.
    scrollContainer.scrollTop = scrollTopBeforeGrow;

    // Opcional: Num próximo frame, pode-se tentar um currentEditor.focus() para
    // sutilmente pedir ao navegador para reavaliar a visibilidade do cursor,
    // agora com o scroll-padding-top já definido e a altura do textarea atualizada.
    // requestAnimationFrame(() => {
    //    currentEditor.focus();
    // });
});
        if (notes[noteId]) notes[noteId].editorElement = editor; else { console.error(`[Editor] Nota ${noteId} desapareceu.`); return null; }
        notesContentAreaForMednotes.appendChild(editor); autoGrowTextarea(editor);
        return editor;
    };

    const createNoteTab = (noteId, title) => {
        if (!notesTabNav || !addNoteTabBtn) { initializeMedNotesSelectors(); if (!notesTabNav || !addNoteTabBtn) return null; }
        if (notesTabNav.querySelector(`.note-tab-button[data-note-id="${noteId}"]`)) return notesTabNav.querySelector(`.note-tab-button[data-note-id="${noteId}"]`);
        const tabButton = document.createElement('button'); tabButton.dataset.noteId = noteId; tabButton.classList.add('note-tab-button'); tabButton.draggable = true;
        const titleContainer = document.createElement('div'); titleContainer.className = 'note-title-container'; titleContainer.title = title;
        const titleSpan = document.createElement('span'); titleSpan.className = 'note-title-span'; titleSpan.textContent = title; titleContainer.appendChild(titleSpan);
        titleContainer.addEventListener('dblclick', (e) => { e.stopPropagation(); const cNId = tabButton.dataset.noteId; if (!notes[cNId]) return; handleTitleEdit(cNId, tabButton, titleContainer, titleSpan); });
        const closeBtn = document.createElement('button'); closeBtn.className = 'close-note-btn'; closeBtn.setAttribute('aria-label', 'Excluir Nota'); closeBtn.title = 'Excluir Nota'; closeBtn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
        closeBtn.addEventListener('click', (e) => { e.stopPropagation(); const cNId = tabButton.dataset.noteId; deleteNote(cNId); });
        tabButton.appendChild(titleContainer); tabButton.appendChild(closeBtn);
        tabButton.addEventListener('click', (e) => { const cNId = tabButton.dataset.noteId; if (e.target.closest('.close-note-btn') || tabButton.querySelector('.edit-title-input')) return; if(activeNoteId !== cNId) activateNoteTab(cNId); else if(notes[cNId]?.editorElement) notes[cNId].editorElement.focus(); });
        tabButton.addEventListener('dragstart', (e) => { if (tabButton.querySelector('.edit-title-input')) { e.preventDefault(); return; } draggedNoteTab = tabButton; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', noteId); } catch(err) { e.dataTransfer.setData('text', noteId); } setTimeout(() => { if(draggedNoteTab === tabButton) tabButton.style.opacity = '0.5'; }, 0); });
        tabButton.addEventListener('dragend', () => { if (draggedNoteTab === tabButton) draggedNoteTab.style.opacity = '1'; if (draggedNoteTab === tabButton) draggedNoteTab = null; if(notesTabNav) notesTabNav.querySelectorAll('.drag-over-placeholder-note').forEach(el => el.remove()); });
        notesTabNav.insertBefore(tabButton, addNoteTabBtn);
        return tabButton;
    };

    const handleCategoryNameEdit = (categoryId, catButton, nameContainer, nameSpan) => {
        const isCreating = categoryId.startsWith('new-cat-');
        if (!isCreating && !categories[categoryId]) { catButton.remove(); updateAddCategoryButtonAttention(); return; }
        if (catButton.querySelector('.edit-category-name-input')) return;
        const originalName = isCreating ? "" : categories[categoryId].name;
        const input = document.createElement('input');
        input.type = 'text'; input.className = 'edit-category-name-input'; input.value = originalName; input.placeholder = "Nome da Categoria";
        input.setAttribute('aria-label', isCreating ? 'Nome da Nova Categoria' : 'Editar nome da categoria');
        nameContainer.style.display = 'none';
        const firstActionButton = catButton.querySelector('.category-action-button.delete-cat-btn');
        if (firstActionButton) catButton.insertBefore(input, firstActionButton); else catButton.appendChild(input);
        input.focus(); if (!isCreating && originalName) input.select();
        let escaped = false;
        const handleKeyDown = (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } else if (e.key === 'Escape') { escaped = true; input.blur(); } e.stopPropagation(); };
        const completeEdit = async () => {
            input.removeEventListener('blur', completeEdit); input.removeEventListener('keydown', handleKeyDown);
            const newName = input.value.trim();
            if (escaped || (!newName && isCreating)) {
                if (input.parentNode === catButton) catButton.removeChild(input);
                if (isCreating) {
                    catButton.remove();
                    delete categories[categoryId];
                    updateAddCategoryButtonAttention();
                } else {
                    nameContainer.style.display = ''; nameSpan.textContent = originalName; nameContainer.title = `Categoria: ${escapeHtml(originalName)}`;
                }
                return;
            }
            if (newName && (newName !== originalName || isCreating)) {
                showStatus(isCreating ? 'Criando categoria...' : 'Renomeando...', 'saving');
                if (input.parentNode === catButton) catButton.removeChild(input); nameContainer.style.display = ''; nameSpan.textContent = newName; nameContainer.title = `Categoria: ${escapeHtml(newName)}`;
                try {
                    let data;
                    if (isCreating) {
                        const existingOrders = Object.values(categories).map(c=>c.order).filter(o=>typeof o==='number'&&isFinite(o));
                        const newOrder = existingOrders.length > 0 ? Math.max(...existingOrders) + 1 : 0;
                        const response = await fetch('api/notes_handler.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'create_category', name: newName, order: newOrder }) });
                        if (!response.ok) throw await response.json().catch(()=>({message:`Erro HTTP ${response.status}`}));
                        data = await response.json(); if (!data.success || !data.category?.id) throw new Error(data.message || "Erro ao criar no servidor.");
                        const newRealCatId = data.category.id.toString();
                        categories[newRealCatId] = { name: newName, order: data.category.order !== undefined ? data.category.order : newOrder, isTemporary: false };
                        delete categories[categoryId]; catButton.dataset.categoryId = newRealCatId;
                        updateCategoryOrderAndSave(); showStatus('Categoria criada!', 'success');
                        updateAddCategoryButtonAttention();
                        selectCategory(newRealCatId);
                    } else {
                        const response = await fetch('api/notes_handler.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'update_category', category_id: categoryId, name: newName }) });
                        if (!response.ok) throw await response.json().catch(()=>({message:`Erro HTTP ${response.status}`}));
                        data = await response.json(); if (!data.success) throw new Error(data.message || "Erro ao renomear no servidor.");
                        categories[categoryId].name = newName; showStatus('Categoria renomeada!', 'success');
                    }
                } catch (error) { console.error(`[HandleCatEdit] Erro API:`, error); showStatus(`Erro: ${error.message || 'Falha na operação.'}`, 'error', 5000); nameSpan.textContent = originalName; nameContainer.title = `Categoria: ${escapeHtml(originalName)}`; if (!isCreating && categories[categoryId]) categories[categoryId].name = originalName; else if (isCreating) { catButton.remove(); delete categories[categoryId]; updateAddCategoryButtonAttention(); } }
            } else { if (input.parentNode === catButton) catButton.removeChild(input); nameContainer.style.display = ''; nameSpan.textContent = originalName; nameContainer.title = `Categoria: ${escapeHtml(originalName)}`; }
        };
        input.addEventListener('blur', completeEdit); input.addEventListener('keydown', handleKeyDown); input.addEventListener('click', (e) => e.stopPropagation());
    };

    const handleTitleEdit = (noteId, tabButton, titleContainer, titleSpan) => {
        if (!notes[noteId] || document.querySelector('.edit-title-input')) return;
        const originalTitle = notes[noteId].title; const input = document.createElement('input');
        input.type = 'text'; input.className = 'edit-title-input'; input.value = originalTitle; input.setAttribute('aria-label', 'Editar título da nota');
        titleContainer.style.display = 'none'; const closeBtnRef = tabButton.querySelector('.close-note-btn');
        if (closeBtnRef) tabButton.insertBefore(input, closeBtnRef); else tabButton.appendChild(input);
        input.focus(); input.select(); let escaped = false;
        const completeEdit = () => {
            input.removeEventListener('blur', completeEdit); input.removeEventListener('keydown', handleKeyDown);
            if (input.parentNode === tabButton) tabButton.removeChild(input); titleContainer.style.display = '';
            if (escaped) { titleSpan.textContent = originalTitle; titleContainer.title = originalTitle; return; }
            const newTitle = input.value.trim();
            if (newTitle && newTitle !== originalTitle && notes[noteId]) {
                notes[noteId].title = newTitle; notes[noteId].saved = false; notes[noteId].titleIsAutomatic = false;
                titleSpan.textContent = newTitle; titleContainer.title = newTitle;
                updatePlaceholderVisibility();
                showStatus('Título alterado. Salvando...', 'info'); saveNote(noteId, false);
            } else { titleSpan.textContent = originalTitle; titleContainer.title = originalTitle; }
        };
        const handleKeyDown = (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } else if (e.key === 'Escape') { escaped = true; input.blur(); } e.stopPropagation(); };
        input.addEventListener('blur', completeEdit); input.addEventListener('keydown', handleKeyDown); input.addEventListener('click', (e) => e.stopPropagation());
    };

    const activateNoteTab = (noteId) => {
        const noteIdStr = noteId ? noteId.toString() : null;
        if (!noteIdStr) { console.error("[ActivateNote] ID inválido."); return; }
        if (!notesTabNav || !notesContentAreaForMednotes) { initializeMedNotesSelectors(); if (!notesTabNav || !notesContentAreaForMednotes) return; }

        if (!notes[noteIdStr] || notes[noteIdStr].categoryId !== activeCategoryId) { if (activeNoteId === noteIdStr) activeNoteId = null; updatePlaceholderVisibility(); saveAppState(); return; }
        if (activeNoteId === noteIdStr) { if (notes[noteIdStr]?.editorElement?.focus) setTimeout(()=>notes[noteIdStr].editorElement.focus(),0); return; }

        if (activeNoteId && notes[activeNoteId]?.categoryId === activeCategoryId) { const prevTab = notesTabNav.querySelector(`.note-tab-button[data-note-id="${activeNoteId}"]`); if (prevTab) prevTab.classList.remove('active-note-tab'); if (notes[activeNoteId].editorElement) notes[activeNoteId].editorElement.style.display = 'none'; }
        else if (activeNoteId) { notesTabNav.querySelectorAll('.note-tab-button.active-note-tab').forEach(t=>t.classList.remove('active-note-tab')); notesContentAreaForMednotes.querySelectorAll('.note-editor').forEach(ed=>ed.style.display='none'); }

        const newActiveTab = notesTabNav.querySelector(`.note-tab-button[data-note-id="${noteIdStr}"]`);
        if (!newActiveTab) { activeNoteId = null; updatePlaceholderVisibility(); saveAppState(); return; }
        newActiveTab.classList.add('active-note-tab'); activeNoteId = noteIdStr;

        let editor = notes[activeNoteId].editorElement;
        if (!editor || !document.body.contains(editor)) { if (editor && !document.body.contains(editor)) { editor.remove(); notes[activeNoteId].editorElement = null; } editor = createEditor(activeNoteId); if (!editor) { activeNoteId = null; updatePlaceholderVisibility(); saveAppState(); return; } }
        else { if (editor.value !== (notes[activeNoteId].content || '')) editor.value = notes[activeNoteId].content || ''; }

        editor.style.display = 'block';
        autoGrowTextarea(editor);
        setTimeout(() => editor.focus(), 50);
        updatePlaceholderVisibility();

        const noteStatusElement = document.getElementById('note-status');
        if (notes[activeNoteId].saved) { if (noteStatusElement && !noteStatusElement.classList.contains('error') && !noteStatusElement.classList.contains('saving')) { noteStatusElement.textContent = ''; noteStatusElement.className = 'status-message bar-status'; } }
        else { showStatus('Alterações não salvas...', 'info', 5000); }

        saveAppState();
        if (newActiveTab.scrollIntoView) newActiveTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        requestAnimationFrame(adjustStickyLayout);
   };

    const selectCategory = (categoryIdToSelect) => {
        const categoryIdStr = categoryIdToSelect ? categoryIdToSelect.toString() : null;
        if (!categoryIdStr || !categories[categoryIdStr] || activeCategoryId === categoryIdStr) return;
        if (!categoryTabsContainer || !notesContentAreaForMednotes || !placeholder || !notesTabNav) { initializeMedNotesSelectors(); if (!categoryTabsContainer || !notesContentAreaForMednotes || !placeholder || !notesTabNav) return; }

        if (activeCategoryId && categories[activeCategoryId]) { const prevBtn = categoryTabsContainer.querySelector(`.category-tab-button[data-category-id="${activeCategoryId}"]`); if (prevBtn) prevBtn.classList.remove('active-category-tab'); }
        activeCategoryId = categoryIdStr; activeNoteId = null;
        const newCatButton = categoryTabsContainer.querySelector(`.category-tab-button[data-category-id="${activeCategoryId}"]`);
        if (newCatButton) newCatButton.classList.add('active-category-tab');
        notesContentAreaForMednotes.querySelectorAll('.note-editor').forEach(editor => editor.style.display = 'none');
        placeholder.style.display = 'flex';
        renderNoteTabsForActiveCategory();
        let noteIdToActivate = null;
        const lastActiveNoteIdForThisCat = localStorage.getItem('mednotesLastActiveNoteId');
        if (lastActiveNoteIdForThisCat && notes[lastActiveNoteIdForThisCat]?.categoryId === activeCategoryId) noteIdToActivate = lastActiveNoteIdForThisCat;
        else { const firstNote = notesTabNav.querySelector('.note-tab-button[data-note-id]'); if (firstNote) noteIdToActivate = firstNote.dataset.noteId; }
        if (noteIdToActivate) { setTimeout(() => { if (activeCategoryId === categoryIdStr && notes[noteIdToActivate]) activateNoteTab(noteIdToActivate); else { updatePlaceholderVisibility(); saveAppState(); }}, 50); }
        else { updatePlaceholderVisibility(); saveAppState(); }
    };

    const addNewNote = (noteData = null) => {
        console.log('[AddNewNote] Iniciando. Categoria ativa:', activeCategoryId, 'Dados da nota:', noteData);

        if (noteData && noteData.id) {
            let noteId = noteData.id.toString();
            let title = noteData.title ? noteData.title.trim() : `Nota ${noteId}`;
            if (!title) title = `Nota ${noteId}`;
            let content = noteData.content || '';
            let savedState = true;
            let categoryId = noteData.category_id ? noteData.category_id.toString() : null;
            let titleIsAutomatic = false;

            if (!categoryId || !categories[categoryId]) {
                console.warn(`[AddNewNote] Categoria ${categoryId} inválida para nota ${noteId} carregada. Tentando fallback.`);
                categoryId = activeCategoryId || Object.keys(categories).find(id => categories[id] && !categories[id].isTemporary);
                if (!categoryId) {
                    console.error("[AddNewNote] CRÍTICO: Nenhuma categoria válida para associar nota carregada.");
                    showStatus("Erro crítico: Não foi possível associar a nota carregada a uma categoria.", "error", 0);
                    return;
                }
            }
            if (notes[noteId]) {
                console.warn("[AddNewNote] Nota existente já carregada, não adicionando novamente:", noteId);
                return;
            }
            notes[noteId] = { title, content, saved: savedState, editorElement: null, categoryId, titleIsAutomatic };
            console.log('[AddNewNote] Nota existente adicionada ao estado:', noteId);

        } else {
            console.log('[AddNewNote] Tentando criar nova nota pelo usuário.');
            if (!activeCategoryId) {
                showStatus("Selecione ou crie uma categoria primeiro!", "error");
                console.error("[AddNewNote] ERRO: Nenhuma categoria ativa para criar nota.");

                const currentAddCategoryBtn = document.getElementById('add-category-btn');
                if (currentAddCategoryBtn) {
                    currentAddCategoryBtn.classList.add('alert-animation');
                    setTimeout(() => {
                        currentAddCategoryBtn.classList.remove('alert-animation');
                    }, 600);
                }
                return;
            }

            initializeMedNotesSelectors();
            if (!notesTabNav || !addNoteTabBtn) {
                console.error("[AddNewNote] Elementos da UI de notas não encontrados após inicializar seletores.");
                showStatus("Erro na interface ao tentar criar nota.", "error");
                return;
            }

            console.log(`[AddNewNote] Categoria ativa para nova nota: ${activeCategoryId}`);
            let noteId = `new-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`;
            let title = DEFAULT_NEW_NOTE_TITLE;
            let content = '';
            let savedState = false;
            let categoryId = activeCategoryId;
            let titleIsAutomatic = true;

            notes[noteId] = { title, content, saved: savedState, editorElement: null, categoryId, titleIsAutomatic };
            console.log('[AddNewNote] Nova nota temporária criada no estado:', noteId);

            const tabButton = createNoteTab(noteId, title);

            if (tabButton) {
                console.log(`[AddNewNote] Aba da nota ${noteId} criada. Ativando e salvando.`);
                activateNoteTab(noteId);
                showStatus('Nova nota. Salvando...', 'info');
                saveNote(noteId, false);
            } else {
                console.error(`[AddNewNote] ERRO: Falha ao criar aba para nota ${noteId}.`);
                if (notes[noteId]) delete notes[noteId];
                showStatus('Erro ao criar aba da nota.', 'error');
            }
        }
    };

    const closeNoteUI = (noteIdToClose) => {
        const noteIdStr = noteIdToClose.toString(); const note = notes[noteIdStr];
        if (!notesTabNav) { initializeMedNotesSelectors(); if (!notesTabNav) return; }

        const tabButton = notesTabNav.querySelector(`.note-tab-button[data-note-id="${noteIdStr}"]`); if (tabButton) tabButton.remove();
        if (note?.editorElement) { if(document.body.contains(note.editorElement)) note.editorElement.remove(); note.editorElement = null; }
        if (notes[noteIdStr]) delete notes[noteIdStr];
        saveAppState();
        if (activeNoteId === noteIdStr) {
            activeNoteId = null; const remainingTabs = notesTabNav.querySelectorAll('.note-tab-button[data-note-id]');
            if (remainingTabs.length > 0) { const nextId = remainingTabs[remainingTabs.length - 1].dataset.noteId; activateNoteTab(nextId); }
            else { updatePlaceholderVisibility(); const noteStatusEl = document.getElementById('note-status'); if (noteStatusEl && noteStatusEl.className.includes('status-message')) noteStatusEl.textContent = ''; }
        } else { updatePlaceholderVisibility(); }
        requestAnimationFrame(adjustStickyLayout);
    };

    const saveNote = async (noteId, isAutoSave = false) => {
        const originalNoteId = noteId.toString();
        if (notes[originalNoteId]?.isCurrentlySaving) { if (isAutoSave) notes[originalNoteId].saved = false; return; }
        if (!notes[originalNoteId]) { if (!isAutoSave) showStatus('Erro: Nota não encontrada.', 'error'); return; }
        notes[originalNoteId].isCurrentlySaving = true; isSaving = true; updatePlaceholderVisibility();
        const currentTitle = notes[originalNoteId].title; const currentContent = notes[originalNoteId].content; const currentCategoryId = notes[originalNoteId].categoryId;
        if (!currentCategoryId) { showStatus('Erro: Categoria da nota desconhecida!', 'error'); delete notes[originalNoteId].isCurrentlySaving; isSaving = false; updatePlaceholderVisibility(); return; }
        const isTempNewNote = originalNoteId.startsWith('new-');
        if (isAutoSave && isTempNewNote && !currentContent.trim() && currentTitle.trim() === DEFAULT_NEW_NOTE_TITLE) { notes[originalNoteId].saved = false; delete notes[originalNoteId].isCurrentlySaving; isSaving = false; updatePlaceholderVisibility(); return; }
        if (!isAutoSave) showStatus('Salvando...', 'saving'); else if (originalNoteId === activeNoteId) showStatus('Salvando automaticamente...', 'saving');
        const noteDataToSend = { id: isTempNewNote ? null : originalNoteId, title: currentTitle, content: currentContent, category_id: currentCategoryId };
        let savedNoteId = null; let finalNoteId = originalNoteId;
        try {
            const response = await fetch('api/notes_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'save', note: noteDataToSend }) });
            if (!response.ok) { let eM = `Erro HTTP ${response.status}`; try { const d=await response.json(); eM=d.message||eM; } catch(e){} throw new Error(eM); }
            const data = await response.json(); if (!data.success || !data.note_id) throw new Error(data.message || 'Resposta inválida do servidor.');
            savedNoteId = data.note_id.toString();
            if (!notes[originalNoteId] && !notes[savedNoteId]) {}
            else if (isTempNewNote && originalNoteId !== savedNoteId) {
                 finalNoteId = savedNoteId; notes[savedNoteId] = { ...notes[originalNoteId] };
                 if (notes[originalNoteId].editorElement) { notes[savedNoteId].editorElement = notes[originalNoteId].editorElement; if(notes[savedNoteId].editorElement.id === `editor-${originalNoteId}`) notes[savedNoteId].editorElement.id = `editor-${savedNoteId}`; }
                 delete notes[originalNoteId];
                 if (notesTabNav) { const tabBtn = notesTabNav.querySelector(`.note-tab-button[data-note-id="${originalNoteId}"]`); if (tabBtn) tabBtn.dataset.noteId = savedNoteId; }
                 if (activeNoteId === originalNoteId) activeNoteId = savedNoteId;
                 saveAppState();
            }
            if (notes[finalNoteId]) { notes[finalNoteId].saved = true; if (!isAutoSave) showStatus('Nota salva!', 'success'); else if (finalNoteId === activeNoteId) showStatus('Salvo.', 'success', 1500); }
            else { showStatus('Erro ao finalizar salvamento local.', 'error'); }
        } catch (error) { const noteToMarkUnsaved = notes[originalNoteId] || notes[finalNoteId]; if (noteToMarkUnsaved) { showStatus(`Erro ao salvar: ${error.message}`, 'error', 5000); noteToMarkUnsaved.saved = false; }}
        finally { const finalObj = notes[finalNoteId] || notes[originalNoteId]; if (finalObj) delete finalObj.isCurrentlySaving; isSaving = false; updatePlaceholderVisibility(); }
    };

    const deleteNote = async (noteIdToDelete) => {
        const noteIdStr = noteIdToDelete ? noteIdToDelete.toString() : null;
        if (!noteIdStr || !notes[noteIdStr]) { showStatus('Erro: Nota não encontrada.', 'error'); return; }
        const noteTitle = notes[noteIdStr].title;
        if (!confirm(`Tem certeza que deseja excluir a nota "${escapeHtml(noteTitle)}"?`)) return;
        if (noteIdStr.startsWith('new-')) { closeNoteUI(noteIdStr); showStatus('Nota não salva descartada.', 'info'); return; }
        showStatus('Excluindo nota...', 'saving'); isSaving = true; if(notes[noteIdStr]) notes[noteIdStr].isCurrentlySaving = true; updatePlaceholderVisibility();
        try {
            const response = await fetch('api/notes_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete', note_id: noteIdStr }) });
            if (!response.ok) { let eM=`Erro ${response.status}`; try{const d=await response.json();eM=d.message||eM;}catch(e){} throw new Error(eM); }
            const data = await response.json(); if (data.success) { closeNoteUI(noteIdStr); setTimeout(() => showStatus('Nota excluída!', 'success', 2500), 50); }
            else throw new Error(data.message || 'Erro ao excluir no servidor.');
        } catch (error) { showStatus(`Erro ao excluir: ${error.message}`, 'error', 5000); if(notes[noteIdStr]) delete notes[noteIdStr].isCurrentlySaving; }
        finally { const noteAfter = notes[noteIdStr]; if(noteAfter) delete noteAfter.isCurrentlySaving; isSaving = false; updatePlaceholderVisibility(); }
    };

    const deleteCategory = async (categoryIdToDelete) => {
        const categoryIdStr = categoryIdToDelete ? categoryIdToDelete.toString() : null;
        if (!categoryTabsContainer || !placeholder ) { initializeMedNotesSelectors(); if (!categoryTabsContainer || !placeholder) return; }

        if (!categoryIdStr || !categories[categoryIdStr]) { showStatus('Erro: Categoria não encontrada.', 'error'); return; }
        const categoryName = categories[categoryIdStr].name; const notesInCategory = Object.values(notes).filter(note => note.categoryId === categoryIdStr);
        const noteCount = notesInCategory.length; let userConfirmed = false; let deleteAssociatedNotes = false;
        if (noteCount > 0) { userConfirmed = confirm(`A categoria "${escapeHtml(categoryName)}" tem ${noteCount} nota(s).\n\nATENÇÃO: Excluir a categoria TAMBÉM EXCLUIRÁ TODAS AS SUAS NOTAS PERMANENTEMENTE.\n\nDeseja continuar?`); if (userConfirmed) deleteAssociatedNotes = true; else return; }
        else { userConfirmed = confirm(`Excluir categoria VAZIA "${escapeHtml(categoryName)}"?`); if (!userConfirmed) return; deleteAssociatedNotes = false; }

        const catButton = categoryTabsContainer ? categoryTabsContainer.querySelector(`.category-tab-button[data-category-id="${categoryIdStr}"]`) : null;
        const originalCategoryData = { ...categories[categoryIdStr] };
        const originalNotesInCategory = notesInCategory.map(note => ({ id: Object.keys(notes).find(id => notes[id] === note), data: { ...note } }));

        if (catButton) catButton.remove();
        delete categories[categoryIdStr];
        if (deleteAssociatedNotes) {
            originalNotesInCategory.forEach(noteInfo => {
                if (noteInfo.id && notes[noteInfo.id]) {
                    if (activeNoteId === noteInfo.id) { activeNoteId = null; if(notes[noteInfo.id].editorElement && document.body.contains(notes[noteInfo.id].editorElement)) notes[noteInfo.id].editorElement.style.display = 'none'; }
                    delete notes[noteInfo.id];
                }
            });
        }
        if (activeCategoryId === categoryIdStr) {
            activeCategoryId = null; renderNoteTabsForActiveCategory(); updatePlaceholderVisibility();
            const firstRemainingCatBtn = categoryTabsContainer ? categoryTabsContainer.querySelector('.category-tab-button[data-category-id]') : null;
            if (firstRemainingCatBtn) selectCategory(firstRemainingCatBtn.dataset.categoryId);
            else { if (placeholder) placeholder.style.display = 'flex'; }
        }
        saveAppState(); requestAnimationFrame(adjustStickyLayout);
        showStatus('Excluindo categoria...', 'saving'); isSaving = true;

        try {
            const response = await fetch('api/notes_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_category', category_id: categoryIdStr, delete_notes: deleteAssociatedNotes }) });
            if (!response.ok) { let eM = `Erro ${response.status}`; try { const d = await response.json(); eM = d.message || eM; } catch (e) {} throw new Error(eM); }
            const data = await response.json(); if (!data.success) throw new Error(data.message || "Erro ao excluir no servidor.");
            showStatus('Categoria excluída!', 'success');
            updateCategoryOrderAndSave();
        } catch (error) {
            showStatus(`Erro: ${error.message}`, 'error', 5000);
            categories[categoryIdStr] = originalCategoryData;
            if (deleteAssociatedNotes) { originalNotesInCategory.forEach(noteInfo => { if (noteInfo.id) notes[noteInfo.id] = noteInfo.data; }); }
            renderCategoryTabs();
            if (activeCategoryId === null && Object.keys(categories).includes(categoryIdStr)) selectCategory(categoryIdStr);
            else if (activeCategoryId) renderNoteTabsForActiveCategory();
            updatePlaceholderVisibility(); saveAppState();
        } finally { isSaving = false; updateAddCategoryButtonAttention(); }
    };

    const createNewCategory = () => {
        if (!categoryTabsContainer) { initializeMedNotesSelectors(); if (!categoryTabsContainer) return; }
        if (categoryTabsContainer.querySelector('.edit-category-name-input') || categoryTabsContainer.querySelector('[data-category-id^="new-cat-"]')) { const exIn = categoryTabsContainer.querySelector('.edit-category-name-input'); if(exIn) exIn.focus(); return; }
        const tempCatId = `new-cat-${Date.now()}-${Math.random().toString(36).substring(2,7)}`;
        const tempOrder = Object.keys(categories).length > 0 ? Math.max(0, ...Object.values(categories).map(c => c.order ?? -1)) + 1 : 0;
        categories[tempCatId] = { name: "", order: tempOrder, isTemporary: true };
        const tempCatButton = createCategoryTabButton(tempCatId, "");
        if (tempCatButton) { const nameCont = tempCatButton.querySelector('.category-name-container'); const nameSp = tempCatButton.querySelector('.category-name-span'); if (nameCont && nameSp) handleCategoryNameEdit(tempCatId, tempCatButton, nameCont, nameSp); else { tempCatButton.remove(); delete categories[tempCatId]; updateAddCategoryButtonAttention(); }}
        else { showStatus("Erro ao iniciar criação.", "error"); delete categories[tempCatId]; updateAddCategoryButtonAttention(); }
    };

    const loadInitialData = async () => {
        initializeMedNotesSelectors();
        if (!categoryNav) { showStatus("Erro: UI de categoria (MedNotes) não encontrada.", "error", 0); dataLoaded = true; return; }
        if (dataLoaded) { requestAnimationFrame(adjustStickyLayout); return; }
        dataLoaded = true; showStatus('Carregando dados...', 'info');

        if(notesTabNav) notesTabNav.querySelectorAll('.note-tab-button[data-note-id]').forEach(btn => btn.remove());
        if(categoryTabsContainer) categoryTabsContainer.querySelectorAll('.category-tab-button[data-category-id]').forEach(btn => btn.remove());
        if(notesContentAreaForMednotes) notesContentAreaForMednotes.querySelectorAll('.note-editor').forEach(editor => editor.remove());
        notes = {}; categories = {}; activeCategoryId = null; activeNoteId = null; updatePlaceholderVisibility();
         try {
             const catResponse = await fetch('api/notes_handler.php', { method: 'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'load_categories'}) });
             if (!catResponse.ok) throw new Error(`Categorias: ${catResponse.status}`);
             const catData = await catResponse.json(); if (!catData.success || !Array.isArray(catData.categories)) throw new Error(catData.message || 'Erro servidor (categorias).');
             catData.categories.forEach(cat => { if (cat?.id) categories[cat.id.toString()] = { name: cat.name, order: cat.order }; });

             const notesResponse = await fetch('api/notes_handler.php', { method: 'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'load'}) });
             if (!notesResponse.ok) throw new Error(`Notas: ${notesResponse.status}`);
             const notesData = await notesResponse.json(); if (!notesData.success || !Array.isArray(notesData.notes)) throw new Error(notesData.message || 'Erro servidor (notas).');
             notesData.notes.forEach(note => { if (note?.id && note.category_id && categories[note.category_id.toString()]) addNewNote({ id: note.id, title: note.title, content: note.content, category_id: note.category_id }); else console.warn(`Nota ${note?.id} ignorada.`, note); });
             renderCategoryTabs();
             let categoryIdToActivate = null;
             const lastActiveCatId = localStorage.getItem('mednotesLastActiveCategoryId');
             if (lastActiveCatId && categories[lastActiveCatId]) categoryIdToActivate = lastActiveCatId;
             else if (Object.keys(categories).length > 0 && categoryTabsContainer) { const firstBtn = categoryTabsContainer.querySelector('.category-tab-button[data-category-id]'); if (firstBtn) categoryIdToActivate = firstBtn.dataset.categoryId; }

             if (categoryIdToActivate) {
                selectCategory(categoryIdToActivate);
             } else {
                updatePlaceholderVisibility(); saveAppState(); if (placeholder) placeholder.style.display = 'flex';
             }
             showStatus(Object.keys(notes).filter(id => !id.startsWith('new-')).length > 0 ? "Dados carregados." : "Nenhuma nota encontrada. Crie uma categoria!", "success", 3500);

         } catch (error) { showStatus(`Erro ao carregar: ${error.message}`, 'error', 6000); dataLoaded = false; updatePlaceholderVisibility(); if (placeholder) placeholder.style.display = 'flex';}
         finally {
            requestAnimationFrame(adjustStickyLayout);
            updateAddCategoryButtonAttention();
         }
    };

    async function updateCategoryOrderAndSave() {
        if (!categoryTabsContainer) { initializeMedNotesSelectors(); if (!categoryTabsContainer) return; }
        const orderedCatElems = Array.from(categoryTabsContainer.querySelectorAll('.category-tab-button[data-category-id]'));
        const orderedCatIds = orderedCatElems.map(btn => btn.dataset.categoryId).filter(id => !id.startsWith('new-cat-'));
        const catUpdates = []; orderedCatIds.forEach((id, index) => { if (categories[id]) { categories[id].order = index; catUpdates.push({ id: id, order: index }); }});
        try { localStorage.setItem('mednotesCategoryOrder', JSON.stringify(orderedCatIds)); if (catUpdates.length > 0) { showStatus("Atualizando ordem...", "saving"); const response = await fetch('api/notes_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'update_category_order', category_orders: catUpdates }) }); if (!response.ok) { const errD = await response.json().catch(() => ({ message: `Erro HTTP ${response.status}` })); throw new Error(errD.message || `Erro servidor: ${response.status}`); } const data = await response.json(); if (!data.success) throw new Error(data.message || "Falha ao atualizar ordem."); showStatus("Ordem atualizada!", "success", 1500); }}
        catch (e) { showStatus(`Erro salvar ordem: ${e.message}`, "error", 3000); }
        requestAnimationFrame(adjustStickyLayout);
    }


    // =====================================================
    // --- LÓGICA DE EXECUÇÃO INICIAL (MedNotes) ---
    // =====================================================
    initializeMedNotesSelectors();

    if (addNoteTabBtn) addNoteTabBtn.addEventListener('click', () => addNewNote());
    if (addCategoryBtn) addCategoryBtn.addEventListener('click', () => createNewCategory());

    // Lógica de Drag and Drop
    if (categoryTabsContainer) {
        categoryTabsContainer.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; const target = e.target.closest('.category-tab-button'); categoryTabsContainer.querySelectorAll('.drag-over-placeholder-cat').forEach(el => el.remove()); if (target && draggedCategory && target !== draggedCategory) { const rect = target.getBoundingClientRect(); const offsetX = e.clientX - rect.left; const pEl = document.createElement('div'); pEl.className = 'drag-over-placeholder-cat'; if (offsetX > rect.width / 2) target.parentNode.insertBefore(pEl, target.nextSibling); else target.parentNode.insertBefore(pEl, target); } else if (!target && draggedCategory && addCategoryBtn && e.target.closest('#add-category-btn')) { const pEl = document.createElement('div'); pEl.className = 'drag-over-placeholder-cat'; categoryTabsContainer.insertBefore(pEl, addCategoryBtn); }});
        categoryTabsContainer.addEventListener('dragleave', (e) => { const rel = e.relatedTarget; if (!rel || (rel !== categoryTabsContainer && !categoryTabsContainer.contains(rel))) categoryTabsContainer.querySelectorAll('.drag-over-placeholder-cat').forEach(el => el.remove()); });
        categoryTabsContainer.addEventListener('drop', async (e) => { e.preventDefault(); categoryTabsContainer.querySelectorAll('.drag-over-placeholder-cat').forEach(el => el.remove()); if (!draggedCategory) return; const dropTgtBtn = e.target.closest('.category-tab-button'); const dropTgtAddBtn = addCategoryBtn && e.target.closest('#add-category-btn'); if (dropTgtBtn && dropTgtBtn !== draggedCategory) { const rect = dropTgtBtn.getBoundingClientRect(); const offsetX = e.clientX - rect.left; if (offsetX > rect.width / 2) dropTgtBtn.parentNode.insertBefore(draggedCategory, dropTgtBtn.nextSibling); else dropTgtBtn.parentNode.insertBefore(draggedCategory, dropTgtBtn); } else if (dropTgtAddBtn) { categoryTabsContainer.insertBefore(draggedCategory, addCategoryBtn); } else if (!dropTgtBtn && draggedCategory && categoryTabsContainer.contains(e.target)) { categoryTabsContainer.insertBefore(draggedCategory, addCategoryBtn); } if (draggedCategory) draggedCategory.style.opacity = '1'; await updateCategoryOrderAndSave(); draggedCategory = null; });
    }
    if (notesTabNav) {
        notesTabNav.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; const target = e.target.closest('.note-tab-button'); notesTabNav.querySelectorAll('.drag-over-placeholder-note').forEach(el => el.remove()); if (target && draggedNoteTab && target !== draggedNoteTab) { const rect = target.getBoundingClientRect(); const offsetX = e.clientX - rect.left; const pEl = document.createElement('div'); pEl.className = 'drag-over-placeholder-note'; if (offsetX > rect.width / 2) target.parentNode.insertBefore(pEl, target.nextSibling); else target.parentNode.insertBefore(pEl, target); } else if (!target && draggedNoteTab && addNoteTabBtn && e.target.closest('#add-note-tab-btn')) { const pEl = document.createElement('div'); pEl.className = 'drag-over-placeholder-note'; notesTabNav.insertBefore(pEl, addNoteTabBtn); }});
        notesTabNav.addEventListener('dragleave', (e) => { const rel = e.relatedTarget; if (!rel || (rel !== notesTabNav && !notesTabNav.contains(rel))) notesTabNav.querySelectorAll('.drag-over-placeholder-note').forEach(el => el.remove()); });
        notesTabNav.addEventListener('drop', (e) => { e.preventDefault(); notesTabNav.querySelectorAll('.drag-over-placeholder-note').forEach(el => el.remove()); if (!draggedNoteTab) return; const dropTgtBtn = e.target.closest('.note-tab-button'); const dropTgtAddBtn = addNoteTabBtn && e.target.closest('#add-note-tab-btn'); if (dropTgtBtn && dropTgtBtn !== draggedNoteTab) { const rect = dropTgtBtn.getBoundingClientRect(); const offsetX = e.clientX - rect.left; if (offsetX > rect.width / 2) dropTgtBtn.parentNode.insertBefore(draggedNoteTab, dropTgtBtn.nextSibling); else dropTgtBtn.parentNode.insertBefore(draggedNoteTab, dropTgtBtn); } else if (dropTgtAddBtn) { notesTabNav.insertBefore(draggedNoteTab, addNoteTabBtn); } else if (!dropTgtBtn && draggedNoteTab && notesTabNav.contains(e.target)) { notesTabNav.insertBefore(draggedNoteTab, addNoteTabBtn); } if (draggedNoteTab) draggedNoteTab.style.opacity = '1'; draggedNoteTab = null; saveAppState(); });
    }

    // Lógica de ativação de aba e carregamento inicial de dados
    tabLinks.forEach(link => {
        link.addEventListener('click', () => {
            const tabId = link.getAttribute('data-tab');
            if (!tabId || link.classList.contains('active')) return;
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => { c.classList.remove('active'); c.style.display = 'none'; });
            link.classList.add('active');
            const activeContent = document.getElementById(tabId);
            if (activeContent) {
                activeContent.classList.add('active');
                activeContent.style.display = 'flex'; // Alterado para flex para corresponder ao seu CSS
                if (tabId === 'mednotes') {
                    initializeMedNotesSelectors(); // Garante que os seletores de mednotes estão prontos
                    if (!dataLoaded) { loadInitialData(); }
                    else { setTimeout(() => requestAnimationFrame(adjustStickyLayout), 0); }
                } else {
                    // Se outra aba for ativada, podemos querer reajustar o layout também
                    // ou executar lógicas específicas para essa aba.
                    // Exemplo: se houver elementos sticky na aba de calculadoras
                    // requestAnimationFrame(adjustStickyLayoutForCalculators);
                }
            } else { console.error(`[Tabs] Conteúdo aba ${tabId} não encontrado.`); }
        });
    });

    const activeTabOnLoad = document.querySelector('.tab-nav .tab-link.active');
    if (activeTabOnLoad) {
        const activeTabIdOnLoad = activeTabOnLoad.dataset.tab;
        const activeContentOnLoad = document.getElementById(activeTabIdOnLoad);
        if (activeContentOnLoad) {
            activeContentOnLoad.classList.add('active');
            activeContentOnLoad.style.display = 'flex'; // Alterado para flex
            if (activeTabIdOnLoad === 'mednotes') {
                initializeMedNotesSelectors();
                if (!dataLoaded) { loadInitialData(); }
                else { setTimeout(() => requestAnimationFrame(adjustStickyLayout), 0); }
            }
        }
    } else {
        const firstTabLink = document.querySelector('.tab-nav .tab-link');
        if (firstTabLink) { firstTabLink.click(); }
    }

    window.addEventListener('resize', debounce(adjustStickyLayout, 150));

    console.log("[Init] Script MedNotes finalizado e reorganizado.");
}); // Fim do DOMContentLoaded

// --- Funções das Calculadoras ---
// Estas funções são chamadas pelos atributos onclick="" no HTML

function calcularIMC() {
    const pesoEl = document.getElementById('peso_imc');
    const alturaCmEl = document.getElementById('altura_imc_cm');
    const resultadoEl = document.getElementById('resultadoIMC');
    const classificacaoEl = document.getElementById('classificacaoIMC');

    if (!pesoEl || !alturaCmEl || !resultadoEl || !classificacaoEl) {
        console.error("Elementos da calculadora de IMC não encontrados!");
        alert("Erro ao encontrar os campos da calculadora de IMC.");
        return;
    }

    const peso = parseFloat(pesoEl.value);
    const alturaCm = parseFloat(alturaCmEl.value);

    // Resetar resultados antes de novas validações ou cálculos
    resultadoEl.textContent = "--";
    classificacaoEl.textContent = "--";

    if (isNaN(peso) || peso <= 0) {
        alert("Por favor, insira um peso válido.");
        pesoEl.focus();
        return;
    }

    if (isNaN(alturaCm) || alturaCm <= 0) {
        alert("Por favor, insira uma altura válida em centímetros.");
        alturaCmEl.focus();
        return;
    }

    const alturaM = alturaCm / 100;
    const imc = peso / (alturaM * alturaM);

    resultadoEl.textContent = imc.toFixed(2);

    let classificacao = "";
    if (imc < 18.5) {
        classificacao = "Abaixo do peso";
    } else if (imc < 24.9) {
        classificacao = "Peso normal";
    } else if (imc < 29.9) {
        classificacao = "Sobrepeso";
    } else if (imc < 34.9) {
        classificacao = "Obesidade Grau I";
    } else if (imc < 39.9) {
        classificacao = "Obesidade Grau II";
    } else {
        classificacao = "Obesidade Grau III";
    }
    classificacaoEl.textContent = classificacao;
}

function calcularIdadeGestacional() {
    const dumEl = document.getElementById('dum');
    const dataUsgEl = document.getElementById('data_usg');
    const idadeUsgSemanasEl = document.getElementById('idade_usg_semanas');
    const idadeUsgDiasEl = document.getElementById('idade_usg_dias');
    const dataReferenciaEl = document.getElementById('data_referencia');
    const resultadoIdadeGestacionalEl = document.getElementById('resultadoIdadeGestacional');
    const resultadoDPPEl = document.getElementById('resultadoDPP');

    if (!dumEl || !dataUsgEl || !idadeUsgSemanasEl || !idadeUsgDiasEl || !dataReferenciaEl || !resultadoIdadeGestacionalEl || !resultadoDPPEl) {
        console.error("Elementos da calculadora de idade gestacional não encontrados!");
        alert("Erro ao encontrar os campos da calculadora de idade gestacional.");
        return;
    }
    
    resultadoIdadeGestacionalEl.textContent = "--";
    resultadoDPPEl.textContent = "--";

    const dumValue = dumEl.value;
    const dataUsgValue = dataUsgEl.value;
    const idadeUsgSemanas = parseInt(idadeUsgSemanasEl.value);
    const idadeUsgDias = parseInt(idadeUsgDiasEl.value);
    const dataReferenciaValue = dataReferenciaEl.value;

    // Adicionar T00:00:00 para evitar problemas de fuso horário local ao converter string para Date
    const dumDate = dumValue ? new Date(dumValue + "T00:00:00") : null;
    const dataUsgDate = dataUsgValue ? new Date(dataUsgValue + "T00:00:00") : null;
    let dataReferenciaDate = dataReferenciaValue ? new Date(dataReferenciaValue + "T00:00:00") : new Date();
    
    // Normalizar data de referência para meia-noite para consistência nos cálculos de diferença
    dataReferenciaDate.setHours(0,0,0,0);


    let igSemanasFinal, igDiasFinal, dppDateFinal;

    // Prioridade para USG se dados completos e válidos forem fornecidos
    const usgDataValida = dataUsgDate && 
                          !isNaN(idadeUsgSemanas) && idadeUsgSemanas >= 0 &&
                          !isNaN(idadeUsgDias) && idadeUsgDias >= 0 && idadeUsgDias <= 6;

    if (usgDataValida) {
        // Calcular a "DUM estimada" com base na USG
        // Dias totais de gestação na data da USG
        const diasGestacaoNaUSG = (idadeUsgSemanas * 7) + idadeUsgDias;
        
        // Data estimada da DUM com base na USG (dataUSG - diasGestacaoNaUSG)
        let dumEstimadaPelaUSG = new Date(dataUsgDate);
        dumEstimadaPelaUSG.setDate(dumEstimadaPelaUSG.getDate() - diasGestacaoNaUSG);

        // Diferença em milissegundos entre a data de referência e a DUM estimada pela USG
        const diffMs = dataReferenciaDate.getTime() - dumEstimadaPelaUSG.getTime();
        if (diffMs < 0) {
            alert("A data de referência não pode ser anterior à concepção estimada pela USG.");
            return;
        }
        const diasTotaisGestacao = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        igSemanasFinal = Math.floor(diasTotaisGestacao / 7);
        igDiasFinal = diasTotaisGestacao % 7;

        dppDateFinal = new Date(dumEstimadaPelaUSG);
        dppDateFinal.setDate(dppDateFinal.getDate() + 280); // DPP é 280 dias (40 semanas) a partir da DUM (real ou estimada)

    } else if (dumDate) {
        // Cálculo pela DUM
        const diffMs = dataReferenciaDate.getTime() - dumDate.getTime();
        if (diffMs < 0) {
            alert("A data de referência não pode ser anterior à DUM.");
            return;
        }
        const diasTotaisGestacao = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        igSemanasFinal = Math.floor(diasTotaisGestacao / 7);
        igDiasFinal = diasTotaisGestacao % 7;

        dppDateFinal = new Date(dumDate);
        dppDateFinal.setDate(dppDateFinal.getDate() + 280);
    } else {
        alert("Por favor, informe a DUM ou os dados completos e válidos do Ultrassom (Data do USG, Semanas e Dias).");
        return;
    }
    
    if (igSemanasFinal !== undefined && igDiasFinal !== undefined) {
        resultadoIdadeGestacionalEl.textContent = `${igSemanasFinal} semanas e ${igDiasFinal} dias`;
    }
    if (dppDateFinal) {
         const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
         resultadoDPPEl.textContent = dppDateFinal.toLocaleDateString('pt-BR', options);
    }
}

function calcularCockcroftGault() {
    const idadeEl = document.getElementById('idade_cg');
    const pesoEl = document.getElementById('peso_cg');
    const creatininaSericaEl = document.getElementById('creatinina_serica_cg');
    const sexoEl = document.getElementById('sexo_cg');
    const resultadoEl = document.getElementById('resultadoCG');

    if (!idadeEl || !pesoEl || !creatininaSericaEl || !sexoEl || !resultadoEl) {
        console.error("Elementos da calculadora de Cockcroft-Gault não encontrados!");
        alert("Erro ao encontrar os campos da calculadora de Cockcroft-Gault.");
        return;
    }

    const idade = parseInt(idadeEl.value);
    const peso = parseFloat(pesoEl.value);
    const creatininaSerica = parseFloat(creatininaSericaEl.value);
    const sexo = sexoEl.value;

    resultadoEl.textContent = "--"; 

    if (isNaN(idade) || idade <= 0) {
        alert("Por favor, insira uma idade válida.");
        idadeEl.focus(); return;
    }
    if (isNaN(peso) || peso <= 0) {
        alert("Por favor, insira um peso válido.");
        pesoEl.focus(); return;
    }
    if (isNaN(creatininaSerica) || creatininaSerica <= 0) {
        alert("Por favor, insira um valor de creatinina sérica válido.");
        creatininaSericaEl.focus(); return;
    }

    let clearance = ((140 - idade) * peso) / (72 * creatininaSerica);

    if (sexo === "feminino") {
        clearance *= 0.85;
    }

    resultadoEl.textContent = clearance.toFixed(2);
}

function calcularSC() { // Superfície Corporal (Mosteller)
    const pesoEl = document.getElementById('peso_sc');
    const alturaEl = document.getElementById('altura_sc'); 
    const resultadoEl = document.getElementById('resultadoSC');
    
    if (!pesoEl || !alturaEl || !resultadoEl) {
        console.error("Elementos da calculadora de Superfície Corporal não encontrados!");
        alert("Erro ao encontrar os campos da calculadora de Superfície Corporal.");
        return;
    }

    const peso = parseFloat(pesoEl.value);
    const alturaCm = parseFloat(alturaEl.value);

    resultadoEl.textContent = "--"; 

    if (isNaN(peso) || peso <= 0) {
        alert("Por favor, insira um peso válido.");
        pesoEl.focus(); return;
    }
    if (isNaN(alturaCm) || alturaCm <= 0) {
        alert("Por favor, insira uma altura válida em centímetros.");
        alturaEl.focus(); return;
    }

    const sc = Math.sqrt((peso * alturaCm) / 3600);

    resultadoEl.textContent = sc.toFixed(2);
}
