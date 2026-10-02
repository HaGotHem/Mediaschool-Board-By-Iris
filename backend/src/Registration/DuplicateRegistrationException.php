<?php
declare(strict_types=1);
namespace Board\Registration;

/** Ce visiteur est déjà inscrit à ce salon. */
final class DuplicateRegistrationException extends \RuntimeException
{
}
