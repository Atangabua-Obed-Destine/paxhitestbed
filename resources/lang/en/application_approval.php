<?php

return [
    // The four steps, in the order they are given. These replace the lines on
    // the printed "For Official Use Only" block.
    'received' => 'Application received',
    'documents' => 'Documents verified',
    'board' => 'Admissions board decision',
    'final' => 'Final approval',

    'approved_note' => ':step given.',
    'returned_title' => 'Returned for :step',

    // How a decision reads in the history.
    'decision' => [
        'approved' => 'Given',
        'rejected' => 'Refused',
        'returned' => 'Returned',
    ],

    'reason_required' => 'Say why. The applicant is told, and the school has to be able to explain the decision.',
    'cannot_reject' => ':step cannot reject an application. Return it instead, so the applicant can put it right.',
    'return_forward' => 'An application can only be returned to a step it has already passed.',
    'unknown_step' => 'That is not one of the approval steps.',
    'not_permitted' => 'You do not hold the permission for :step.',
    'not_current' => ':step cannot be given yet. :current is waiting.',
    'already_complete' => 'Every approval has already been given.',
    'already_rejected' => 'This application was refused. Return it to an earlier step to reopen it.',

    'not_approved_for_conversion' => 'This application has not been fully approved, so no student record can be created from it yet. Waiting on: :step.',
    'rejected_for_conversion' => 'This application was refused, so no student record can be created from it.',
];
