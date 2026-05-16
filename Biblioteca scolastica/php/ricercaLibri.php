<?php
/*
 * Autore: [Diego Scatizzi]
 * Data: 2026
 * Versione: 1.2
 * Funzionalità: Homepage biblioteca scolastica con ricerca e autocomplete
 */
 
session_start(); // avvia la sessione per leggere i dati del login
 
// Legge nome e cognome dalla sessione. Se non esistono usa valori di default.
// htmlspecialchars converte caratteri speciali (es. <>) in testo sicuro, evitando attacchi XSS
$nome    = isset($_SESSION['nome'])    ? htmlspecialchars($_SESSION['nome'])    : 'Ospite';
$cognome = isset($_SESSION['cognome']) ? htmlspecialchars($_SESSION['cognome']) : '';
 
/*
 * Questo blocco si attiva SOLO quando JavaScript lo chiama in background (AJAX).
 * Parametro in ingresso: $_GET['q'] → testo digitato dall'utente
 * Valore di ritorno: JSON array con titolo e autore dei libri trovati
 */
if (isset($_GET['autocomplete'])) {
 
    // Converte la query in minuscolo e rimuove spazi iniziali/finali
    $q       = strtolower(trim($_GET['q'] ?? ''));
    $results = []; // array vuoto che conterrà i risultati
 
    // Cerca solo se l'utente ha scritto almeno 2 caratteri
    if (strlen($q) >= 2) {
 
        $file = fopen(__DIR__ . '/lista_libri.txt', 'r'); // apre il file in sola lettura
 
        if ($file) {
            fgets($file); // salta la prima riga (intestazione con i nomi delle colonne)
 
            /* Legge riga per riga finché:
             * - non finisce il file (fgets restituisce false)
             * - oppure ha già trovato 8 risultati (limite massimo suggerimenti)
             */
            while (($line = fgets($file)) !== false && count($results) < 8) {
 
                // Divide la riga nelle sue colonne usando ; come separatore
                // Struttura: N_RIGA;ID;TITOLO;AUTORE;GENERE;ANNO;EDITORE;ISBN;LINGUA;PAGINE;DISPONIBILE
                $cols = explode(';', trim($line));
 
                if (count($cols) < 4) continue; // riga malformata, salta
 
                $titolo = $cols[2]; // terza colonna
                $autore = $cols[3]; // quarta colonna
                $isbn   = $cols[7] ?? ''; // ottava colonna (con fallback vuoto se mancante)
 
                // Controlla se la query è contenuta nel titolo, autore o ISBN
                if (
                    str_contains(strtolower($titolo), $q) ||
                    str_contains(strtolower($autore), $q) ||
                    str_contains(strtolower($isbn),   $q)
                ) {
                    $results[] = ['titolo' => $titolo, 'autore' => $autore];
                }
            }
            fclose($file); // chiude il file dopo l'uso
        }
    }
 
    header('Content-Type: application/json'); // dice al browser che risponde in JSON
    echo json_encode($results);               // converte l'array PHP in testo JSON e lo invia
    exit();                                   // ferma l'esecuzione: non deve caricare l'HTML sotto
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Scolastica</title>
</head>
<body>
 
<div>
    <small>Accesso come <strong><?= $nome . ' ' . $cognome ?></strong></small>
    <a href="logout.php">Esci</a>
</div>
 
<h1>Biblioteca Scolastica</h1>
 
<form method="GET" action="risultati.php" id="searchForm">
    <input
        type="text"
        name="q"
        id="searchInput"
        placeholder="Barra di ricerca"
        autocomplete="off"
    >
    <div id="suggerimenti"></div>
    <br>
    <button type="submit">Cerca</button>
</form>
 
<script>
    // Recupera i riferimenti agli elementi HTML che useremo
    const input       = document.getElementById('searchInput'); // campo di testo
    const box         = document.getElementById('suggerimenti'); // div dove mostrare i risultati
    let debounceTimer = null; // variabile per gestire il ritardo (vedi commento sotto)
 
    /*
     * Evento: si attiva ad ogni tasto premuto dentro il campo di ricerca
     * Scopo: chiamare il PHP in background e mostrare i suggerimenti
     */
    input.addEventListener('input', function () {
 
        // DEBOUNCE: cancella il timer precedente ad ogni tasto.
        // Senza debounce ogni singolo tasto farebbe una chiamata al server.
        // Con debounce si aspetta che l'utente smetta di scrivere per 200ms.
        // Es: se scrivo "isa" rapidamente → vengono annullate le chiamate per "i" e "is",
        //     e parte solo quella per "isa" dopo 200ms di pausa.
        clearTimeout(debounceTimer);
 
        const q = this.value.trim(); // prende il testo scritto, rimuovendo spazi
 
        // Se ha scritto meno di 2 caratteri, svuota i suggerimenti e non fa nulla
        if (q.length < 2) { box.innerHTML = ''; return; }
 
        // Avvia il timer: esegue la funzione dopo 200ms di pausa dalla scrittura
        debounceTimer = setTimeout(() => {
 
            /*
             * fetch(): fa una chiamata HTTP in background (AJAX) allo stesso file PHP.
             * Passa due parametri GET:
             *   - autocomplete=1 → dice al PHP di entrare nel blocco dei suggerimenti
             *   - q=testo        → il testo da cercare
             * encodeURIComponent converte caratteri speciali per l'URL (es. spazi → %20)
             */
            fetch(`?autocomplete=1&q=${encodeURIComponent(q)}`)
 
                // quando arriva la risposta, la converte da testo JSON a array JavaScript
                .then(r => r.json())
 
                // riceve l'array di libri trovati e aggiorna la pagina
                .then(libri => {
 
                    // Se il PHP non ha trovato niente, svuota i suggerimenti
                    if (!libri.length) { box.innerHTML = ''; return; }
 
                    /*
                     * .map(): trasforma ogni libro dell'array in un pezzo di HTML.
                     * Per ogni libro crea un <div> cliccabile con "Titolo — Autore".
                     * .replace(/'/g, "\\'") sostituisce gli apostrofi nel titolo
                     *   per evitare che rompano il codice JavaScript dentro onclick.
                     *   Es: "L'isola" → "L\'isola"
                     * .join(''): unisce tutti i <div> in una stringa unica
                     */
                    box.innerHTML = libri.map(l =>
                        `<div onclick="seleziona('${l.titolo.replace(/'/g, "\\'")}')">
                            ${l.titolo} — ${l.autore}
                        </div>`
                    ).join('');
                });
 
        }, 200); // 200ms di attesa prima di fare la chiamata al server
    });
 
    /*
     * Funzione seleziona()
     * Parametro in ingresso: titolo → stringa con il titolo del libro scelto
     * Scopo: inserisce il titolo nel campo di ricerca e invia il form automaticamente
     */
    function seleziona(titolo) {
        input.value   = titolo;       // scrive il titolo nel campo
        box.innerHTML = '';           // chiude i suggerimenti
        document.getElementById('searchForm').submit(); // invia il form
    }
 
    /*
     * Evento sul documento intero: chiude i suggerimenti se l'utente
     * clicca in qualsiasi punto della pagina fuori dal campo o dal box.
     * Parametro: e → l'evento click, contiene e.target (elemento cliccato)
     * .contains() restituisce true se l'elemento cliccato è dentro input o box
     */
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !box.contains(e.target)) {
            box.innerHTML = '';
        }
    });
</script>
 
</body>
</html>