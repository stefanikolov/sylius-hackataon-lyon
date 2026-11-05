Feature: Wishlist API (Stage 4)
  As a customer or an external application
  I want to manage wishlists through an API
  So that wishlist functionality can be used without the storefront

  # Endpoints: /api/v2/shop/wishlists (see plugin/README.md). All Stage 1-3 scenarios must keep working.

  Scenario: Retrieve all wishlists
    Given I am an authenticated API customer
    And I own the following wishlists:
      | Name         |
      | My Favorites |
      | Birthday     |
      | Christmas    |
    When I request my wishlists through the API
    Then the response should be successful
    And the response should contain all three wishlists
    And each wishlist should include its identifier and name
    And wishlists belonging to other customers should not be included

  Scenario: Retrieve a specific wishlist
    Given I own a wishlist named "Birthday"
    And the wishlist contains "T-Shirt" and "Sunglasses"
    When I request the "Birthday" wishlist through the API
    Then the response should be successful
    And the response should contain the wishlist identifier and name
    And the response should contain "T-Shirt" and "Sunglasses"

  Scenario: Create a new wishlist
    Given I am an authenticated API customer
    When I send a request to create a wishlist named "Christmas"
    Then the wishlist should be created
    And the response should indicate successful creation
    And the response should contain the new wishlist identifier
    And the wishlist should be available in subsequent API requests

  Scenario: Rename an existing wishlist
    Given I own a wishlist named "Birthday"
    And the wishlist contains two products
    When I send a request to rename it to "Birthday Ideas"
    Then the request should be successful
    And the wishlist name should be updated to "Birthday Ideas"
    And its existing products should remain unchanged

  Scenario: Delete a wishlist
    Given I own a wishlist named "Christmas"
    And the wishlist contains multiple products
    When I request to delete the "Christmas" wishlist
    Then the request should be successful
    And the wishlist should no longer be available
    And my other wishlists should remain unchanged
    And no products should be deleted from the store catalogue

  Scenario: Add a product to a wishlist
    Given I own a wishlist named "My Favorites"
    And the wishlist does not contain "T-Shirt"
    When I send an API request to add "T-Shirt" to "My Favorites"
    Then the request should be successful
    And "T-Shirt" should appear in the wishlist
    And no other wishlist should be modified

  Scenario: Prevent duplicate wishlist entries
    Given I own a wishlist named "My Favorites"
    And "My Favorites" already contains "T-Shirt"
    When I send an API request to add "T-Shirt" again
    Then the wishlist should still contain only one entry for "T-Shirt"
    And the API should return a meaningful response

  Scenario: Remove a product from a wishlist
    Given I own a wishlist named "Birthday"
    And "Birthday" contains "T-Shirt" and "Sunglasses"
    When I request to remove "T-Shirt" from "Birthday"
    Then the request should be successful
    And "T-Shirt" should no longer belong to "Birthday"
    And "Sunglasses" should remain in the wishlist

  Scenario: Add the same product to multiple wishlists
    Given I own a wishlist named "Birthday"
    And I own another wishlist named "Christmas"
    When I add "T-Shirt" to both wishlists through the API
    Then "T-Shirt" should appear in "Birthday"
    And "T-Shirt" should appear in "Christmas"
    And each wishlist should contain only one entry for that product

  Scenario: Move a product between wishlists
    Given I own a wishlist named "Birthday"
    And I own a wishlist named "Christmas"
    And "Birthday" contains "Sunglasses"
    When I move "Sunglasses" from "Birthday" to "Christmas" through the API
    Then "Sunglasses" should no longer appear in "Birthday"
    And "Sunglasses" should appear in "Christmas"

  Scenario: Reject a wishlist with an invalid name
    Given I am an authenticated API customer
    When I request to create a wishlist with an empty name
    Then the request should be rejected
    And the response should contain a meaningful validation error
    And no wishlist should be created

  Scenario: Prevent unauthorized wishlist access
    Given customer "John" owns a wishlist named "Birthday"
    And customer "Alice" is authenticated through the API
    When Alice attempts to retrieve John's wishlist
    Then access should be denied or the wishlist should be reported as not found
    And John's wishlist data should not be exposed

  Scenario: Prevent unauthorized wishlist modification
    Given customer "John" owns a wishlist named "Birthday"
    And customer "Alice" is authenticated through the API
    When Alice attempts to modify or delete John's wishlist
    Then the operation should be rejected
    And John's wishlist should remain unchanged

  Scenario: Manage a guest wishlist through the API
    Given I am using the API as a guest
    When I create a wishlist named "My Favorites"
    And I add "T-Shirt" to that wishlist
    And I request my wishlists again using the same guest identity
    Then the response should contain "My Favorites"
    And "T-Shirt" should be present in that wishlist
    And I should not be able to access another guest's wishlists

  Scenario: Synchronize API and storefront changes
    Given I own a wishlist named "Birthday"
    And I am logged in to the storefront
    When I add "T-Shirt" to "Birthday" through the API
    And I open "Birthday" in the storefront
    Then "T-Shirt" should be visible in the wishlist
    When I remove "T-Shirt" through the storefront
    And I retrieve "Birthday" through the API
    Then "T-Shirt" should no longer appear in the API response

  Scenario: Handle an unavailable product
    Given I own a wishlist containing a product that is no longer available for purchase
    When I retrieve the wishlist through the API
    Then the product should remain in the wishlist response
    And the response should indicate that the product is unavailable for purchase

  Scenario: Handle a non-existent wishlist
    Given I am an authenticated API customer
    When I request a wishlist that does not exist
    Then the API should return an appropriate not-found response
    And the response should not expose internal implementation details
