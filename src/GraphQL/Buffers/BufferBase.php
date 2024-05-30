<?php

namespace Drupal\graphql\GraphQL\Buffers;

/**
 * Base class for field buffering services.
 */
abstract class BufferBase {

  /**
   * The the array of buffers.
   *
   * @var \SplObjectStorage<object, object>[]
   */
  protected $buffers = [];

  /**
   * The array of result sets.
   *
   * @var \SplObjectStorage<object, object>[]
   */
  protected $results = [];

  /**
   * Returns the bucket name for grouping items together.
   *
   * @param object $item
   *   The item to get the buffer id for.
   *
   * @return string
   *   The buffer id.
   */
  protected function getBufferId($item) {
    return "";
  }

  /**
   * Helper function to create a resolver for a singular buffer.
   *
   * @param object $item
   *   The item to add to the buffer.
   *
   * @return \Closure
   *   The callback to invoke to load the result for this buffer item.
   */
  public function createBufferResolver($item) {
    $bufferId = $this->getBufferId($item);
    if (!isset($this->buffers[$bufferId])) {
      $this->buffers[$bufferId] = new \SplObjectStorage();
    }

    if (!isset($this->results[$bufferId])) {
      $this->results[$bufferId] = new \SplObjectStorage();
    }

    // Add the created item to the buffer.
    $this->buffers[$bufferId]->attach($item, $item);

    // Return a callback that can be used to resolve the buffer item.
    return $this->createResolver($item, $this->buffers[$bufferId], $this->results[$bufferId]);
  }

  /**
   * Creates a callback to invoke to load the result for this buffer item.
   *
   * @param object $item
   *   The item to add to create the resolver for.
   * @param \SplObjectStorage<object, object> $buffer
   *   The buffer.
   * @param \SplObjectStorage<object, object> $result
   *   The result set.
   *
   * @return \Closure
   *   The callback to invoke to load the result for this buffer item.
   */
  protected function createResolver($item, \SplObjectStorage $buffer, \SplObjectStorage $result) {
    // Return the closure that will resolve and return the result for the item.
    return function () use ($item, $buffer, $result) {
      return $this->resolveItem($item, $buffer, $result);
    };
  }

  /**
   * Returns the result of the given item after processing the buffer if needed.
   *
   * @param object $item
   *   The buffer item to retrieve the result for.
   * @param \SplObjectStorage<object, object> $buffer
   *   The buffer.
   * @param \SplObjectStorage<object, object> $result
   *   The result set.
   *
   * @return mixed
   *   The result of resolving the given buffer item.
   */
  protected function resolveItem($item, \SplObjectStorage $buffer, \SplObjectStorage $result) {
    if ($buffer->contains($item)) {
      $results = $this->resolveBuffer($buffer);

      // Remove the resolved items from the buffer and add them to the results.
      $buffer->removeAll($results);
      $result->addAll($results);
    }

    if ($result->contains($item)) {
      return $result[$item];
    }

    throw new \LogicException('Failed to resolve item.');
  }

  /**
   * Resolves the given buffer wholly.
   *
   * @param \SplObjectStorage<object, object> $buffer
   *   The buffer to be resolved wholly.
   *
   * @return \SplObjectStorage<object, object>
   *   The resolved results for the given buffer, keyed by the corresponding
   *   buffer items.
   */
  protected function resolveBuffer(\SplObjectStorage $buffer) {
    // Convert the buffer to an array that we can later use to map the results
    // to the correct batch items.
    $buffer = iterator_to_array($buffer, FALSE);

    // Assign the loaded items to their corresponding batch items.
    $output = new \SplObjectStorage();
    foreach ($this->resolveBufferArray($buffer) as $key => $item) {
      $output->attach($buffer[$key], $item);
    }

    return $output;
  }

  /**
   * Resolve the buffer as an array.
   *
   * Simplifies sub-class implementations by concealing the object storage
   * details of the buffer object.
   *
   * @param array $buffer
   *   The buffer as an array.
   *
   * @return array
   *   The resolved/loaded items.
   */
  protected function resolveBufferArray(array $buffer) {
    throw new \LogicException('Method not implemented.');
  }

}
