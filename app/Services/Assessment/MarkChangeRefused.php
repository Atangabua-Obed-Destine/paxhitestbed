<?php

namespace App\Services\Assessment;

/**
 * A mark change this system will not accept.
 *
 * It exists so that a refusal can be told apart from a fault. The direct edit
 * catches this and only this: anything else - a missing class, a bad column -
 * must surface as the error it is rather than reaching the admin as a sentence
 * that reads like a considered refusal of a legitimate correction.
 */
class MarkChangeRefused extends \RuntimeException
{
}
