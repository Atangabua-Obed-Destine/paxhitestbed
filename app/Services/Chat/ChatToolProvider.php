<?php

namespace App\Services\Chat;

use App\Services\Chat\Tools\Applicant\MyApplicationsTool;
use App\Services\Chat\Tools\Applicant\MyApplicationTimelineTool;
use App\Services\Chat\Tools\Publics\KnowledgeLookupTool;
use App\Services\Chat\Tools\Publics\ListProgrammesTool;
use App\Services\Chat\Tools\Publics\OpenIntakesTool;
use App\Services\Chat\Tools\Staff\CountApplicationsTool;
use App\Services\Chat\Tools\Staff\FindStudentTool;
use App\Services\Chat\Tools\Student\MyAttendanceTool;
use App\Services\Chat\Tools\Student\MyClassRoutineTool;
use App\Services\Chat\Tools\Student\MyExamResultsTool;
use App\Services\Chat\Tools\Student\MyFeesTool;
use App\Services\Chat\Tools\Student\MyProfileTool;

/**
 * The definitive list of capabilities the assistant has.
 *
 * Adding a tool here is the ONLY way to give the assistant a new ability. Kept
 * separate from the service provider so it can be built in a test without
 * booting the container.
 */
class ChatToolProvider
{
    public static function registry(): ToolRegistry
    {
        $registry = new ToolRegistry();

        $registry->registerMany([
            // Public — no personal data, available to every actor.
            new ListProgrammesTool(),
            new OpenIntakesTool(),
            new KnowledgeLookupTool(),

            // Applicant — own application only.
            new MyApplicationsTool(),
            new MyApplicationTimelineTool(),

            // Student — own records only.
            new MyProfileTool(),
            new MyFeesTool(),
            new MyAttendanceTool(),
            new MyExamResultsTool(),
            new MyClassRoutineTool(),

            // Staff — additionally gated by an existing Spatie permission.
            new FindStudentTool(),
            new CountApplicationsTool(),
        ]);

        return $registry;
    }
}
