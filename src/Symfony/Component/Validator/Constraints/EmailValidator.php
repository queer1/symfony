<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Egulias\EmailValidator\EmailValidator as StrictEmailValidator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class EmailValidator extends ConstraintValidator
{
    /**
     * isStrict
     *
     * @var Boolean
     */
    private $isStrict;

    public function __construct($strict = false)
    {
        $this->isStrict = $strict;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Email) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Email');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        if (null === $constraint->strict) {
            $constraint->strict = $this->isStrict;
        }

        if ($constraint->strict && class_exists('\Egulias\EmailValidator\EmailValidator')) {
            $strictValidator = new StrictEmailValidator();
            $valid = $strictValidator->isValid($value, false);
        } elseif ($constraint->strict === true) {
            throw new \RuntimeException('Strict email validation requires egulias/email-validator');
        } else {
            $valid = preg_match('/.+\@.+\..+/', $value);
        }

        if ($valid) {
            $host = substr($value, strpos($value, '@') + 1);
            // Check for host DNS resource records

            if ($valid && $constraint->checkMX) {
                $valid = $this->checkMX($host);
            } elseif ($valid && $constraint->checkHost) {
                $valid = $this->checkHost($host);
            }
        }

        if (!$valid) {
            $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
        }
    }

    /**
     * Check DNS Records for MX type.
     *
     * @param string $host Host
     *
     * @return Boolean
     */
    private function checkMX($host)
    {
        return checkdnsrr($host, 'MX');
    }

    /**
     * Check if one of MX, A or AAAA DNS RR exists.
     *
     * @param string $host Host
     *
     * @return Boolean
     */
    private function checkHost($host)
    {
        return $this->checkMX($host) || (checkdnsrr($host, "A") || checkdnsrr($host, "AAAA"));
    }
}
