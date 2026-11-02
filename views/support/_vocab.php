<?php
// Shared vocabularies for the staff support pages, as window.CDP_CS_VOCAB.
// Included by inbox.php, ticket.php, settings.php and reports.php.
$csJ = function ($v) { return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); };
$csVocab = [
    'statuses' => cdp_csStatuses(), 'priorities' => cdp_csPriorities(), 'categories' => cdp_csCategories(),
    'channels' => cdp_csChannels(), 'languages' => cdp_csLanguages(), 'owners' => cdp_csOwnerStates(),
    'teams' => array_map(function ($t) { return ['id' => $t['id'], 'name' => $t['name'], 'active' => $t['active']]; }, cdp_csTeams()),
    'agents' => array_map(function ($a) { return ['id' => $a['id'], 'name' => $a['name']]; }, cdp_csAgents()),
    'me' => cdp_csUid(), 'admin' => cdp_csIsAdmin($user), 'king' => cdp_csSetting('king_name', 'King'),
    'king_on' => cdp_csKingOn(), 'copilot' => cdp_csCopilotOn(),
];
?>
<script>window.CDP_CS_VOCAB = <?php echo $csJ($csVocab); ?>;</script>
