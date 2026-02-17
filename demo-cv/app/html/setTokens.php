<?php

function setTokens($groups) {
    $groups['ptdev']['issuers']['mbob']['token'] = '';
    $groups['ptdev']['issuers']['tun']['token'] = '';
    $groups['ptdev']['issuers']['uvh']['token'] = '';
    $groups['ptdev']['issuers']['hbot']['token'] = '';
    $groups['ptdev']['issuers']['epi']['token'] = '';
    $groups['ptdev']['issuers']['nlgov']['token'] = '';
    $groups['ptdev']['issuers']['nbgov']['token'] = '';
    $groups['ptdev']['issuers']['sandbox']['token'] = '';
    $groups['ptdev']['issuers']['sandboxver']['token'] = '';

    $groups['pttest']['issuers']['mbob']['token'] = '';
    $groups['pttest']['issuers']['tun']['token'] = '';
    $groups['pttest']['issuers']['uvh']['token'] = '';
    $groups['pttest']['issuers']['hbot']['token'] = '';
    $groups['pttest']['issuers']['epi']['token'] = '';
    $groups['pttest']['issuers']['nlgov']['token'] = '';
    $groups['pttest']['issuers']['nbgov']['token'] = '';
    $groups['pttest']['issuers']['sandbox']['token'] = '';
    $groups['pttest']['issuers']['sandboxver']['token'] = '';

    $groups['ptstage']['issuers']['mbob']['token'] = '';
    $groups['ptstage']['issuers']['tun']['token'] = '';
    $groups['ptstage']['issuers']['uvh']['token'] = '';
    $groups['ptstage']['issuers']['hbot']['token'] = '';
    $groups['ptstage']['issuers']['epi']['token'] = '';
    $groups['ptstage']['issuers']['nlgov']['token'] = '';
    $groups['ptstage']['issuers']['nbgov']['token'] = '';
    $groups['ptstage']['issuers']['sandbox']['token'] = '';
    $groups['ptstage']['issuers']['sandboxver']['token'] = '';

    $groups['ptprod']['issuers']['mbob']['token'] = '';
    $groups['ptprod']['issuers']['tun']['token'] = '';
    $groups['ptprod']['issuers']['uvh']['token'] = '';
    $groups['ptprod']['issuers']['hbot']['token'] = '';
    $groups['ptprod']['issuers']['epi']['token'] = '';
    $groups['ptprod']['issuers']['nlgov']['token'] = '';
    $groups['ptprod']['issuers']['nbgov']['token'] = '';
    $groups['ptprod']['issuers']['sandbox']['token'] = '';
    $groups['ptprod']['issuers']['sandboxver']['token'] = '';

    $groups['pdev']['issuers']['eduid']['token'] = '';
    $groups['pdev']['issuers']['eduidver']['token'] = '';

    $groups['ptest']['issuers']['eduid']['token'] = '';
    $groups['ptest']['issuers']['eduidver']['token'] = '';

    $groups['pstage']['issuers']['eduid']['token'] = '';
    $groups['pstage']['issuers']['eduidver']['token'] = '';

    $groups['pprod']['issuers']['eduid']['token'] = '';
    $groups['pprod']['issuers']['eduidver']['token'] = '';

    $groups['edev']['issuers']['edubadges']['token'] = '';
    $groups['etest']['issuers']['edubadges']['token'] = '';
    $groups['estage']['issuers']['edubadges']['token'] = '';
    $groups['eprod']['issuers']['edubadges']['token'] = '';

    return $groups;
}