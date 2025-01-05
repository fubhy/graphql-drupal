<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql\Kernel\DataProducer;

use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Tests the current_user data producer.
 *
 * @coversDefaultClass \Drupal\graphql\Plugin\GraphQL\DataProducer\User\CurrentUser
 * @group graphql
 */
class CurrentUserTest extends GraphQLTestBase {

  use UserCreationTrait;

  /**
   * Test users.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  protected array $users;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create two test users.
    $this->users = [
      $this->createUser(),
      $this->createUser(),
    ];

    // Log out initially.
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
  }

  /**
   * @covers \Drupal\graphql\Plugin\GraphQL\DataProducer\User\CurrentUser::resolve
   */
  public function testCurrentUser(): void {
    // Initially no user is logged in.
    $result = $this->executeDataProducer('current_user');
    $this->assertInstanceOf(AccountInterface::class, $result);
    $this->assertEquals(0, $result->id());

    // Log in as the first user.
    $this->container->get('current_user')->setAccount($this->users[0]);
    $result = $this->executeDataProducer('current_user');
    $this->assertInstanceOf(AccountInterface::class, $result);
    $this->assertEquals($this->users[0]->id(), $result->id());

    // Log in as the second user.
    $this->container->get('current_user')->setAccount($this->users[1]);
    $result = $this->executeDataProducer('current_user');
    $this->assertInstanceOf(AccountInterface::class, $result);
    $this->assertEquals($this->users[1]->id(), $result->id());

    // Log out again.
    $this->container->get('current_user')->setAccount(User::getAnonymousUser());
    $result = $this->executeDataProducer('current_user');
    $this->assertInstanceOf(AccountInterface::class, $result);
    $this->assertEquals(0, $result->id());
  }

  /**
   * {@inheritdoc}
   */
  protected function executeDataProducer($id, array $contexts = []) {
    /** @var \Drupal\graphql\Plugin\DataProducerPluginManager $manager */
    $manager = $this->container->get('plugin.manager.graphql.data_producer');

    /** @var \Drupal\graphql\Plugin\DataProducerPluginInterface $plugin */
    $plugin = $manager->createInstance($id);

    // The 'user' cache context should be added so that the results will be
    // cached per user.
    $context = $this->prophesize(FieldContext::class);
    $context->addCacheContexts(['user'])->willReturn($context->reveal())->shouldBeCalled();

    return $plugin->resolveField($context->reveal());
  }

}
