<?php
/**
 * src/includes/document-helpers.php
 *
 * Deljena logika za rad sa documents/document_versions (Klauzula 7.5),
 * koju koristi dokumenti.php, a kasnije će i politike.php - politika
 * je samo tanak red u tabeli policies povrh istog dokumenta, pa nema
 * razloga da piše istu insert/verzionisanje logiku iznova.
 *
 * Ovo je logic-only fajl, bez HTML-a - uključuje se sa require, na
 * vrhu modula, pre bilo kakvog izlaza.
 */

declare(strict_types=1);

/**
 * Kreira novi dokument i njegovu prvu verziju u document_versions.
 * Vraća id novog dokumenta.
 *
 * $data očekuje: title, doc_type, classification, current_version
 * (obavezno), i opciono file_reference, owner_id, approved_by,
 * approved_at, next_review_due.
 */
function createDocument(PDO $pdo, int $organizationId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO documents
            (organization_id, title, doc_type, classification, current_version,
             file_reference, owner_id, approved_by, approved_at, next_review_due)
         VALUES
            (:org_id, :title, :doc_type, :classification, :current_version,
             :file_reference, :owner_id, :approved_by, :approved_at, :next_review_due)'
    );
    $stmt->execute([
        'org_id'          => $organizationId,
        'title'           => $data['title'],
        'doc_type'        => $data['doc_type'],
        'classification'  => $data['classification'],
        'current_version' => $data['current_version'],
        'file_reference'  => $data['file_reference'] ?? null,
        'owner_id'        => $data['owner_id'] ?? null,
        'approved_by'     => $data['approved_by'] ?? null,
        'approved_at'     => $data['approved_at'] ?? null,
        'next_review_due' => $data['next_review_due'] ?? null,
    ]);

    $documentId = (int) $pdo->lastInsertId();

    recordDocumentVersion($pdo, $documentId, $data['current_version'], [
        'changed_by'     => $data['approved_by'] ?? null,
        'change_summary' => 'Prva verzija dokumenta.',
        'file_reference' => $data['file_reference'] ?? null,
    ]);

    return $documentId;
}

/**
 * Upisuje novu verziju u istoriju (document_versions) i ažurira
 * documents.current_version na tu verziju - Klauzula 7.5.2.
 *
 * $data opciono: changed_by, change_summary, file_reference.
 */
function recordDocumentVersion(PDO $pdo, int $documentId, string $versionNumber, array $data = []): void
{
    $pdo->prepare(
        'INSERT INTO document_versions (document_id, version_number, changed_by, change_summary, file_reference)
         VALUES (:document_id, :version_number, :changed_by, :change_summary, :file_reference)'
    )->execute([
        'document_id'    => $documentId,
        'version_number' => $versionNumber,
        'changed_by'     => $data['changed_by'] ?? null,
        'change_summary' => $data['change_summary'] ?? null,
        'file_reference' => $data['file_reference'] ?? null,
    ]);

    $pdo->prepare('UPDATE documents SET current_version = :version WHERE id = :id')
        ->execute(['version' => $versionNumber, 'id' => $documentId]);
}
