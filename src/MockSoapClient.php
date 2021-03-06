<?php

declare(strict_types=1);

namespace loophp\MockSoapClient;

use ArrayIterator;
use InfiniteIterator;
use InvalidArgumentException;
use SoapClient;
use SoapFault;
use SoapHeader;

use function array_key_exists;
use function func_get_args;
use function is_callable;

use const ARRAY_FILTER_USE_KEY;

/**
 * Class MockSoapClient.
 */
class MockSoapClient extends SoapClient
{
    /**
     * @var array<int|string, InfiniteIterator>
     */
    private $iterators;

    /**
     * MockSoapClient constructor.
     *
     * @param array<mixed>|callable $responses
     */
    public function __construct($responses = null)
    {
        $responses = (array) $responses;

        if ([] === $responses) {
            throw new InvalidArgumentException('The response argument cannot be empty.');
        }

        $this->iterators = $this->buildIterators($responses);
    }

    /**
     * @param string $function_name
     * @param array<mixed> $arguments
     *
     * @throws \SoapFault
     *
     * @return mixed
     */
    public function __call($function_name, $arguments = [])
    {
        try {
            $response = $this->__soapCall($function_name, $arguments);
        } catch (SoapFault $exception) {
            throw $exception;
        }

        return $response;
    }

    /**
     * @param string $function_name
     * @param array<mixed> $arguments
     * @param array<mixed>|null $options
     * @param array<mixed>|SoapHeader|null $input_headers
     * @param array<mixed>|null $output_headers
     *
     * @throws SoapFault
     *
     * @return mixed
     */
    public function __soapCall(
        $function_name,
        $arguments,
        $options = null,
        $input_headers = null,
        &$output_headers = null
    ) {
        $iterator = true === array_key_exists($function_name, $this->iterators) ?
            $this->iterators[$function_name] :
            $this->iterators['*'];

        $response = $iterator->current();
        $iterator->next();

        if (true === is_callable($response)) {
            return ($response)(...func_get_args());
        }

        if ($response instanceof SoapFault) {
            throw $response;
        }

        return $response;
    }

    /**
     * Build a simple Infinite iterator.
     *
     * @param array<mixed> $data
     *
     * @return InfiniteIterator
     */
    private function buildIterator(array $data): InfiniteIterator
    {
        $iterator = new InfiniteIterator(new ArrayIterator($data));
        $iterator->rewind();

        return $iterator;
    }

    /**
     * Build the structure of iterators.
     *
     * @param array<mixed> $data
     *
     * @return array<int|string, InfiniteIterator>
     */
    private function buildIterators(array $data): array
    {
        $iterators = [
            '*' => $this->buildIterator(array_filter($data, 'is_numeric', ARRAY_FILTER_USE_KEY)),
        ];

        foreach ($data as $key => $response) {
            if (true === is_numeric($key)) {
                continue;
            }

            $iterators[$key] = $this->buildIterator((array) $data[$key]);
        }

        return $iterators;
    }
}
