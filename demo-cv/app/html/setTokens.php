<?php

function setTokens($groups) {
    $groups['ptdev']['issuers']['mbob']['token'] = getenv('ISSUER_DEV_MBOB');
    $groups['ptdev']['issuers']['tun']['token'] = getenv('ISSUER_DEV_TUN');
    $groups['ptdev']['issuers']['uvh']['token'] = getenv('ISSUER_DEV_UVH');
    $groups['ptdev']['issuers']['hbot']['token'] = getenv('ISSUER_DEV_HBOT');
    $groups['ptdev']['issuers']['epi']['token'] = getenv('ISSUER_DEV_EPI');
    $groups['ptdev']['issuers']['nlgov']['token'] = getenv('ISSUER_DEV_NLGOV');
    $groups['ptdev']['issuers']['nbgov']['token'] = getenv('ISSUER_DEV_NBGOV');
    $groups['ptdev']['issuers']['sandbox']['token'] = getenv('ISSUER_DEV_SANDBOX');
    $groups['ptdev']['issuers']['sandboxver']['token'] = getenv('VERIFIER_DEV_SANDBOX');

    $groups['pttest']['issuers']['mbob']['token'] = getenv('ISSUER_TEST_MBOB');
    $groups['pttest']['issuers']['tun']['token'] = getenv('ISSUER_TEST_TUN');
    $groups['pttest']['issuers']['uvh']['token'] = getenv('ISSUER_TEST_UVH');
    $groups['pttest']['issuers']['hbot']['token'] = getenv('ISSUER_TEST_HBOT');
    $groups['pttest']['issuers']['epi']['token'] = getenv('ISSUER_TEST_EPI');
    $groups['pttest']['issuers']['nlgov']['token'] = getenv('ISSUER_TEST_NLGOV');
    $groups['pttest']['issuers']['nbgov']['token'] = getenv('ISSUER_TEST_NBGOV');
    $groups['pttest']['issuers']['sandbox']['token'] = getenv('ISSUER_TEST_SANDBOX');
    $groups['pttest']['issuers']['sandboxver']['token'] = getenv('VERIFIER_TEST_SANDBOX');

    $groups['ptstage']['issuers']['mbob']['token'] = getenv('ISSUER_STAGE_MBOB');
    $groups['ptstage']['issuers']['tun']['token'] = getenv('ISSUER_STAGE_TUN');
    $groups['ptstage']['issuers']['uvh']['token'] = getenv('ISSUER_STAGE_UVH');
    $groups['ptstage']['issuers']['hbot']['token'] = getenv('ISSUER_STAGE_HBOT');
    $groups['ptstage']['issuers']['epi']['token'] = getenv('ISSUER_STAGE_EPI');
    $groups['ptstage']['issuers']['nlgov']['token'] = getenv('ISSUER_STAGE_NLGOV');
    $groups['ptstage']['issuers']['nbgov']['token'] = getenv('ISSUER_STAGE_NBGOV');
    $groups['ptstage']['issuers']['sandbox']['token'] = getenv('ISSUER_STAGE_SANDBOX');
    $groups['ptstage']['issuers']['sandboxver']['token'] = getenv('VERIFIER_STAGE_SANDBOX');

    $groups['ptprod']['issuers']['mbob']['token'] = getenv('ISSUER_PROD_MBOB');
    $groups['ptprod']['issuers']['tun']['token'] = getenv('ISSUER_PROD_TUN');
    $groups['ptprod']['issuers']['uvh']['token'] = getenv('ISSUER_PROD_UVH');
    $groups['ptprod']['issuers']['hbot']['token'] = getenv('ISSUER_PROD_HBOT');
    $groups['ptprod']['issuers']['epi']['token'] = getenv('ISSUER_PROD_EPI');
    $groups['ptprod']['issuers']['nlgov']['token'] = getenv('ISSUER_PROD_NLGOV');
    $groups['ptprod']['issuers']['nbgov']['token'] = getenv('ISSUER_PROD_NBGOV');
    $groups['ptprod']['issuers']['sandbox']['token'] = getenv('ISSUER_PROD_SANDBOX');
    $groups['ptprod']['issuers']['sandboxver']['token'] = getenv('VERIFIER_PROD_SANDBOX');

    $groups['pdev']['issuers']['eduid']['token'] = getenv('ISSUER_DEV_EDUID');
    $groups['pdev']['issuers']['eduidver']['token'] = getenv('VERIFIER_DEV_EDUID');

    $groups['ptest']['issuers']['eduid']['token'] = getenv('ISSUER_TEST_EDUID');
    $groups['ptest']['issuers']['eduidver']['token'] = getenv('VERIFIER_TEST_EDUID');

    $groups['pstage']['issuers']['eduid']['token'] = getenv('ISSUER_STAGE_EDUID');
    $groups['pstage']['issuers']['eduidver']['token'] = getenv('VERIFIER_STAGE_EDUID');

    $groups['pprod']['issuers']['eduid']['token'] = getenv('ISSUER_PROD_EDUID');
    $groups['pprod']['issuers']['eduidver']['token'] = getenv('VERIFIER_PROD_EDUID');

    $groups['edev']['issuers']['edubadges']['token'] = getenv('ISSUER_DEV_EDUBADGES');
    $groups['etest']['issuers']['edubadges']['token'] = getenv('ISSUER_TEST_EDUBADGES');
    $groups['estage']['issuers']['edubadges']['token'] = getenv('ISSUER_STAGE_EDUBADGES');
    $groups['eprod']['issuers']['edubadges']['token'] = getenv('ISSUER_PROD_EDUBADGES');

    return $groups;
}