<?php
declare(strict_types=1);

return [
    'dashboard'=>['label'=>'Dashboard','url'=>'/dashboard.php','permission'=>'orlms.dashboard.view'],
    'ordinances'=>['label'=>'Ordinance Drafting & Encoding','url'=>'/modules/ordinances/index.php','permission'=>'orlms.drafting.view'],
    'resolutions'=>['label'=>'Resolution Drafting & Submission','url'=>'/modules/resolutions/index.php','permission'=>'orlms.drafting.view'],
    'reviews'=>['label'=>'Review & Committee Endorsement','url'=>'/modules/reviews/index.php','permission'=>'orlms.reviews.view'],
    'approvals'=>['label'=>'Approval & Enactment','url'=>'/modules/approvals/index.php','permission'=>'orlms.approvals.view'],
    'publications'=>['label'=>'Publication & Dissemination','url'=>'/modules/publications/index.php','permission'=>'orlms.publications.view'],
    'implementation'=>['label'=>'Implementation Monitoring','url'=>'/modules/implementation/index.php','permission'=>'orlms.implementation.view'],
    'amendments'=>['label'=>'Amendment & Revision Tracking','url'=>'/modules/amendments/index.php','permission'=>'orlms.amendments.view'],
    'reports'=>['label'=>'Reports & Analytics','url'=>'/reports/index.php','permission'=>'orlms.reports.view'],
];
