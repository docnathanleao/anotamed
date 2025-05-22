<?php
// /dashboard_content.php
// Este arquivo assume que a sessão já foi iniciada e as variáveis de sessão
// como $_SESSION["loggedin"], $_SESSION["user_id"], $_SESSION["username"] estão disponíveis.
// É incluído pelo password_entry.php quando o usuário está logado e no perfil correto.

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo "<p>Você precisa estar logado para ver este conteúdo.</p>";
    return; 
}

// require_once "includes/check_auth.php"; // Se não estiver já chamado antes do include
// require_once "includes/db_connect.php"; // Já está no password_entry.php

$username = isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : 'Usuário';
?>
<!-- REMOVIDO: <!DOCTYPE html>, <html>, <head>, <title>, links CSS, <body> -->

<footer class="site-footer-bottom-right">
    synamed © <?php echo date("Y"); ?> <br>
    Por <a href='https://github.com/docnathanleao'>Nathan</a>
</footer>

<header class="main-header">
    <div class="logo-container">
        <img src="images/anotamedlogo.png" alt="AnotaMed Logo" class="logo-image">
        <span class="logo-text">anotamed</span>
    </div>

    <div class="tab-nav"> 
        <button class="tab-link active" data-tab="mednotes"><i class="fas fa-notes-medical"></i> MedNotes</button>
        <button class="tab-link" data-tab="calculators"><i class="fas fa-calculator"></i> Calculadoras</button>
        <button class="tab-link" data-tab="evolutions"><i class="fas fa-file-medical-alt"></i> Evoluções</button>
        <button class="tab-link" data-tab="bulary"><i class="fas fa-pills"></i> Bulário</button>
    </div>

    <div class="user-info">
        <span><i class="fas fa-user-circle"></i> Bem-vindo, <?php echo $username; ?>!</span>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</header>

<div class="tab-container">
    <div class="tab-content-wrapper">
        <div id="mednotes" class="tab-content active">
             <div class="mednotes-interface">
                <div class="sticky-notes-nav-bars">
                    <div class="category-nav" id="category-nav-bar">
                        <span class="category-label">Categorias:</span>
                        <div class="category-tabs-container" id="category-tabs-container">
                            <button id="add-category-btn" title="Criar nova categoria"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="notes-tab-nav">
                        <button id="add-note-tab-btn" title="Criar nova nota na categoria atual"><i class="fas fa-plus"></i> </button>
                    </div>
                </div>

                <div class="notes-content-area">
                    <div class="note-editor-placeholder">
                         <p>Selecione ou crie uma categoria.<br>Depois, selecione ou crie uma nota.</p>
                         <i class="fas fa-folder-plus fa-3x" style="color: #ccc; margin-top: 20px;"></i>
                    </div>
                    <!-- Textareas serão adicionados aqui pelo JS -->
                </div>
                <span id="note-status" class="status-message bar-status" aria-live="polite"></span>
            </div>
        </div>
        <div id="calculators" class="tab-content">
            <!-- Conteúdo da aba Calculadoras (inalterado) -->
            <div class="calculator-grid">
                <div class="calculator-card">
                    <h3><i class="fas fa-weight-hanging"></i> Índice de Massa Corporal (IMC)</h3>
                    <p class="card-subtitle">(Fórmula Padrão)</p>
                    <div class="form-group">
                        <label for="peso_imc">Peso (kg):</label>
                        <input type="number" id="peso_imc" placeholder="Ex: 70" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="altura_imc_cm">Altura (cm):</label>
                        <input type="number" id="altura_imc_cm" step="1" placeholder="Ex: 175">
                    </div>
                    <button onclick="calcularIMC()"><i class="fas fa-check"></i> Calcular</button>
                    <p class="result-output">Resultado (IMC): <strong id="resultadoIMC">--</strong></p>
                    <p class="result-classification" style="margin-top: 5px; font-size: 0.9em;">Classificação: <strong id="classificacaoIMC">--</strong></p>
                </div>
                <div class="calculator-card">
                    <h3><i class="fas fa-baby"></i> Calculadora de Idade Gestacional</h3>
                    <div class="form-group">
                        <label for="dum">Data da Última Menstruação (DUM):</label>
                        <input type="date" id="dum">
                    </div>
                    <div class="form-group">
                        <label for="data_usg">Data do Ultrassom:</label>
                        <input type="date" id="data_usg">
                    </div>
                    <div class="form-group">
                        <label>Idade Gestacional no USG:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" id="idade_usg_semanas" placeholder="Semanas" min="0" style="width: 100px;">
                            <input type="number" id="idade_usg_dias" placeholder="Dias" min="0" max="6" style="width: 100px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="data_referencia">Data de Referência (opcional):</label>
                        <input type="date" id="data_referencia">
                    </div>
                    <button onclick="calcularIdadeGestacional()"><i class="fas fa-check"></i> Calcular</button>
                    <p class="result-output">Idade Gestacional: <strong id="resultadoIdadeGestacional">--</strong></p>
                    <p class="result-output">Data Provável do Parto (DPP): <strong id="resultadoDPP">--</strong></p>
                </div>
                <div class="calculator-card">
                    <h3><i class="fas fa-tint"></i> Clearance de Creatinina</h3>
                    <p class="card-subtitle">(Cockcroft-Gault)</p>
                    <div class="form-group">
                        <label for="idade_cg">Idade (anos):</label> <input type="number" id="idade_cg" placeholder="Ex: 60">
                    </div>
                    <div class="form-group">
                        <label for="peso_cg">Peso (kg):</label>
                        <input type="number" id="peso_cg" placeholder="Ex: 70" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="creatinina_serica_cg">Creatinina Sérica (mg/dL):</label> <input type="number" step="0.01" id="creatinina_serica_cg" placeholder="Ex: 1.2">
                    </div>
                    <div class="form-group">
                        <label for="sexo_cg">Sexo:</label>
                        <select id="sexo_cg">
                            <option value="masculino">Masculino</option>
                            <option value="feminino">Feminino</option>
                        </select>
                    </div>
                    <button onclick="calcularCockcroftGault()"><i class="fas fa-check"></i> Calcular</button>
                    <p class="result-output">Resultado (Clearance): <strong id="resultadoCG">--</strong> ml/min</p>
                </div>
                <div class="calculator-card">
                    <h3><i class="fas fa-ruler-combined"></i> Superfície Corporal</h3>
                    <p class="card-subtitle">(Fórmula de Mosteller)</p>
                    <div class="form-group">
                        <label for="peso_sc">Peso (kg):</label>
                        <input type="number" id="peso_sc" placeholder="Ex: 70" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="altura_sc">Altura (cm):</label>
                        <input type="number" id="altura_sc" step="1" placeholder="Ex: 175">
                    </div>
                    <button onclick="calcularSC()"><i class="fas fa-check"></i> Calcular</button>
                    <p class="result-output">Resultado (SC): <strong id="resultadoSC">--</strong> m²</p>
                </div>
            </div>
        </div>
        <div id="evolutions" class="tab-content">
            <!-- Conteúdo da aba Evoluções (inalterado) -->
             <div class="tool-section">
                <h3>Modelo GINECO</h3>
                <textarea readonly class="template-textarea">
###### CONSULTA GINECOLÓGICA  URGÊNCIA - DATA #####
- NOME:
- PRONTUÁRIO:
- IDADE:
- COMORBIDADES:
- MUC:
- ALERGIAS:
- CIRURGIAS:
# ANTECEDENTES GINECO-OBSTÉTRICOS:
- G P A
- DUM:
# HDA:
# IS:
- QUEIXAS GINECOLOGICAS:  NEGA: CORRIMENTOS, DISPAREUNIA E SINUSORRAGIA
- QUEIXAS URINÁRIAS: NEGA: DISURIA, POLACIURIA E INCONTINÊNCIA
- HABITO INTESTINAL REGULAR E FISIOLÓGICO
# EXAME FÍSICO:
BEG, LOTE, AAA, NORMOCORADA, HIDRATADA
SINAIS VITAIS: PA: MMHG / FC:  BPM
ACV: RCR 2T BNF SS
AR: MV + SRA
ABD: RHA +, FLACIDO, INDOLOR A PALPAÇÃO SUPERFICIAL E PROFUNDA, SVM OU MASSAS, DB NEGATIVA
MMII: SEM EDEMAS, PANTURILHAS LIVRES
# EXAMES COMPLEMENTARES:
# CD ORIENTADA POR STAFF DR.(A):
- ORIENTO PACIENTE SOBRE O QUADRO, TRATAMENTO E SEGUIMENTO PROPOSTO
- ORIENTO SINAIS DE ALARME
INTERNO:
                </textarea>
                <button onclick="copyTemplate(this)" data-original-text="Copiar Modelo"><i class="fas fa-copy"></i> Copiar Modelo</button>
            </div>
             <div class="tool-section">
                <h3>Modelo de Admissão Hospitalar</h3>
                <textarea readonly class="template-textarea">
### EVOLUÇÃO DE URGÊNCIA OBSTÉTRICA - MATERNIDADE HC-UFG ###
DATA: DD/MM/AAAA  |  HORÁRIO DA AVALIAÇÃO: HH:MM
## IDENTIFICAÇÃO: I
NOME COMPLETO:
IDADE:  ANOS
Nº PRONTUÁRIO:
PROCEDENCIA:
## HISTÓRICO OBSTÉTRICO:
PARIDADE: G P C N A
NASCIDOS VIVOS: [NÚMERO]
INTERVALO INTERPARTAL: [TEMPO DESDE O ÚLTIMO PARTO EM ANOS/MESES]
ANTECEDENTE DE PARTO PREMATURO: [SIM/NÃO - SE SIM, COM QUAL IG?]
ANTECEDENTE DE CURETAGEM/AMIU:
COMPLICAÇÕES EM GESTAÇÕES/PARTOS ANTERIORES:
ANTECEDENTE DE CIRURGIA PRÉVIA:
## GESTAÇÃO ATUAL:
IDADE GESTACIONAL (IG):
PRÉ-NATAL: [SIM/NÃO - SE SIM, ONDE REALIZOU? Nº DE CONSULTAS: ???]
TIPO SANGUÍNEO / FATOR RH: [GRUPO ABO] / [RH POSITIVO/NEGATIVO] - COOMBS INDIRETO (SE RH NEGATIVO): [RESULTADO E DATA, SE DISPONÍVEL ???]
SOROLOGIAS:
>> APAE  I ():
>> APAE II ():
STREPTOCOCCUS GRUPO B (SWAB VAGINAL/RETAL 35-37 SEM): [POSITIVO/NEGATIVO/NÃO REALIZADO/AGUARDANDO RESULTADO] - DATA COLETA: ???
GLICEMIA DE JEJUM (GJ): [VALOR] MG/DL - DATA: ???
TESTE ORAL DE TOLERÂNCIA À GLICOSE (TOTG 75G): JEJUM: [VALOR] / 1H: [VALOR] / 2H: [VALOR] MG/DL - DATA: ???
VACINAÇÃO (DTPA, INFLUENZA): [ATUALIZADA/PENDENTE/RECUSOU]
## ANTECEDENTES PESSOAIS:
COMORBIDADES:
MEDICAÇÕES DE USO CONTÍNUO (MUC):
ALERGIAS:
TABAGISMO/ETILISMO/USO DE DROGAS ILÍCITAS:
# ESCALAS NA MATERNIDADE:
- ROBSON (OLHAR TABELA AO LADO DO COMPUTADOR):
- MEOWS:
- RISCO DE HPP (OLHAR TABELA AO LADO DO COMPUTADOR):
- QSOFA:
# QP:
# HDA:
RELATA INÍCIO DE [SINTOMA PRINCIPAL] HÁ [TEMPO]. CARACTERIZA COMO [DESCRIÇÃO: TIPO DE DOR, LOCALIZAÇÃO, IRRADIAÇÃO, INTENSIDADE 0-10, FATORES DE MELHORA/PIORA]. ASSOCIADO A [SINTOMAS ASSOCIADOS]. REFERE [OU NEGA] METROSSÍSTOLES (CONTRAÇÕES), DESCREVENDO FREQUÊNCIA [X CONTR./10 MIN], DURAÇÃO [SEGUNDOS] E INTENSIDADE [+/++/+++]. REFERE [OU NEGA] SANGRAMENTO VAGINAL (DESCREVER QUANTIDADE, COR, ASPECTO - COÁGULOS?). REFERE [OU NEGA] PERDA DE LÍQUIDO AMNIÓTICO (DESCREVER QUANTIDADE, COR, ODOR). REFERE [OU NEGA] DIMINUIÇÃO OU AUSÊNCIA DE MOVIMENTAÇÃO FETAL PERCEBIDA. REFERE [OU NEGA] CEFALEIA, ESCOTOMAS VISUAIS, DOR EPIGÁSTRICA/EM HIPOCÔNDRIO DIREITO. REFERE [OU NEGA] QUEIXAS URINÁRIAS (DISÚRIA, POLACIÚRIA). REFERE [OU NEGA] FEBRE, CALAFRIOS, CORRIMENTO VAGINAL PATOLÓGICO (DESCREVER COR, ODOR, PRURIDO). .
NEGA: SANGRAMENTOS, METROSSISTOLES, CORRIMENTO VAGINAL, PERDAS VAGINAIS, QUEIXAS URINARIAS.
# EXAME FÍSICO:
BEG, AAA, CORADA E HIDRATADA
SINAIS VITAIS: PA: MMHG / FC:  BPM
ACV: RCR, 2T , BNF, SS
AR: MV+, SRA, SEM SINAIS DE DESCONFORTO RESPIRATÓRIO
DINAMICA UTERINA: AUSENTE
TÔNUS UTERINO:
AFU:  CM;
BCF:  BPM- SEM DESACELERAÇÕES
MOVIMENTOS FETAIS: PRESENTES
ESPECULAR (SE REALIZADO): COLO TRÓFICO, ORIFÍCIO EXTERNO PUNTIFORME, VAGINA COM RUGOSIDADES TRÓFICAS E ÚMIDA. CONTEUDO VAGINAL FISIOLOGICO
TV (SE REALIZADO): COLO POSTERIOR, IMPERVIO, GROSSO.
MMII: AUSÊNCIA DE EDEMA. PANTURRILHAS LIVRES E INDOLORES.
 # EXAMES COMPLEMENTARES:
>> APAE<<
- APAE I (DATA):
- APAE II (DATA):
>> USG<<
- USG 1T
- USG MORFOLOGICO:
- ÚLTIMO USG: IG, APRESENTAÇÃO, PFE, PLACENTA, ILA, MBV, DOPPLER (SE TIVER FEITO)
SEMPRE RECALCULAR PESO PELO HADLOCK (SITE PERINATOLOGY)
 # HD:
1.  [EX: GESTAÇÃO TÓPICA DE X SEM + Y DIAS EM TRABALHO DE PARTO ATIVO, FASE ATIVA]
2.
# CONDUTA DISCUTIDA COM STAFF DR.(A):
- PACIENTE E ACOMPANHANTE ORIENTADOS SOBRE O QUADRO CLÍNICO ATUAL, HIPÓTESES DIAGNÓSTICAS, PLANO PROPOSTO. ESCLARECIDAS AS DÚVIDAS.
INTERNO: NATHAN LUIZ GONÇALVES LEÃO
                </textarea>
                 <button onclick="copyTemplate(this)" data-original-text="Copiar Modelo"><i class="fas fa-copy"></i> Copiar Modelo</button>
            </div>
            <div class="tool-section">
                <h3>Modelo de EXAMES GO</h3>
                <textarea readonly class="template-textarea">
EXAMES URGÊNCIA MATERNIDADE
# ADMISSÃO:
1) Tipo sanguíneo
2) Coombs indireto (se Rh negativo)
3) Teste rápido para HIV
4) Teste rápido para Sífilis
5) Sorologia IGM e IGG toxoplasmose  (se suscetível)
6) Hemograma se risco de HPP
# PRÉ ECLAMPSIA
1) Tipo sanguíneo com
2) Coombs indireto (se Rh negativo)
3) Teste rápido para HIV
4) Teste rápido para Sífilis
5) Sorologia IGM e IGG toxoplasmose (se suscetível)
6) Hemograma
7) DHL
8) ÁCIDO URICO
9) TGO
10) TGP
11) BT E FRAÇÕES
12) CREATININA
13) CREATININA AMOSTRA ISOLADA
14) PROTEÍNA AMOSTRA ISOLADA
15) EAS
16) UREIA
AVALIAR NECESSIDADE DE:
17) PROTEINÚRIA 24 HRS
# DM2
1) Tipo sanguíneo
2) Coombs indireto (se Rh negativo)
3) Teste rápido para HIV
4) Teste rápido para Sífilis
5) Sorologia IGM e IGG toxoplasmose (se susceptível)
6) Hemograma
7) HBA1C
8) UREIA
9) CREATININA
AVALIAR NECESSIDADE DE:
10) LIPIDOGRAMA
11) TSH
12) T4L
# MOLA HIDATIFORME
1) Tipo sanguíneo
2) Coombs indireto (se Rh negativo)
3) Teste rápido para HIV
4) Teste rápido para Sífilis
5) Sorologia IGM e IGG toxoplasmose (se susceptível)
6) BHCG
7) HEMOGRAMA
...
SE MTX (adicionar tbm)
8) UREIA
9) CREATININA
10) TGO
10) TGP
SE NTG
RAIO X DE TÓRAX
# ROPREMA
1) Tipo sanguíneo
2) Coombs indireto (se Rh negativo)
3)Teste rápido para HIV
4) Teste rápido para Sífilis
5)Sorologia IGM e IGG toxoplasmose (se susceptível)
6) HEMOGRAMA
7)PCR
8)EAS
9)UROCULTURA
SE DESCONHECIDO
10)SWAB VAGINAL E RETAL SGB
                </textarea>
                 <button onclick="copyTemplate(this)" data-original-text="Copiar Modelo"><i class="fas fa-copy"></i> Copiar Modelo</button>
            </div>
        </div>
        <div id="bulary" class="tab-content">
            <!-- Conteúdo da aba Bulário (inalterado) -->
            <h2><i class="fas fa-pills"></i> Bulário - Consulta de Medicamentos</h2>
            <div class="search-container">
                <input type="text" id="med-search-input" placeholder="Digite o nome do medicamento...">
                <button id="med-search-btn"><i class="fas fa-search"></i> Buscar</button>
            </div>
            <div id="med-results-area">
                <p>Digite o nome de um medicamento para buscar informações.</p>
            </div>
        </div>
    </div>
</div>

<!-- REMOVIDO: <script> links, pois foram movidos para password_entry.php -->
<!-- REMOVIDO: </body> e </html> -->