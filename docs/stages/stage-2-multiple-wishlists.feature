Feature: Multiple named wishlists (Stage 2)
  As a customer
  I want to create and manage multiple named wishlists
  So that I can organize my favorite products for different purposes

  # All Stage 1 scenarios (stage-1-wishlist.feature) must keep working.

  Scenario: Create a named wishlist
    Given I am browsing the storefront
    When I navigate to the wishlist section
    And I create a new wishlist named "Birthday"
    Then a wishlist named "Birthday" should be created
    And I should see "Birthday" among my available wishlists
    And the new wishlist should initially be empty

  Scenario: View all my wishlists
    Given I have created the following wishlists:
      | Name         |
      | My Favorites |
      | Birthday     |
      | Christmas    |
    When I navigate to the wishlist section
    Then I should see all three wishlists
    And I should be able to select any of them

  Scenario: View products in a selected wishlist
    Given I have a wishlist named "Birthday"
    And "Birthday" contains the following products:
      | Product    |
      | T-Shirt    |
      | Sunglasses |
    And I have another wishlist named "Christmas"
    When I open the "Birthday" wishlist
    Then I should see "T-Shirt" and "Sunglasses"
    And I should not see products belonging exclusively to "Christmas"

  Scenario: Add a product to a selected wishlist
    Given I have the following wishlists:
      | Name         |
      | My Favorites |
      | Birthday     |
    When I browse a product detail page
    And I click "Add to Wishlist"
    And I select "Birthday" as the destination wishlist
    Then the product should be added to "Birthday"
    And the product should not be automatically added to "My Favorites"

  Scenario: Add the same product to different wishlists
    Given I have a wishlist named "Birthday"
    And I have a wishlist named "Christmas"
    And "Birthday" already contains "T-Shirt"
    When I add "T-Shirt" to "Christmas"
    Then "T-Shirt" should exist in both wishlists
    And neither wishlist should contain duplicate entries

  Scenario: Rename a wishlist
    Given I have a wishlist named "Birthday"
    And "Birthday" contains two products
    When I rename the wishlist to "Birthday Ideas"
    Then the wishlist should be named "Birthday Ideas"
    And its two products should remain unchanged
    And the old name "Birthday" should no longer be displayed

  Scenario: Delete a wishlist
    Given I have a wishlist named "Christmas"
    And "Christmas" contains three products
    When I delete the "Christmas" wishlist
    And I confirm the deletion
    Then "Christmas" should no longer appear among my wishlists
    And the three products should no longer be associated with that wishlist
    And my other wishlists should remain unchanged
    And no products should be deleted from the store catalogue

  Scenario: Remove a product from a specific wishlist
    Given I have a wishlist named "Birthday"
    And I have a wishlist named "Christmas"
    And both wishlists contain "T-Shirt"
    When I remove "T-Shirt" from "Birthday"
    Then "T-Shirt" should no longer appear in "Birthday"
    And "T-Shirt" should still appear in "Christmas"

  Scenario: Move a product between wishlists
    Given I have a wishlist named "Birthday"
    And I have a wishlist named "Christmas"
    And "Birthday" contains "Sunglasses"
    When I move "Sunglasses" from "Birthday" to "Christmas"
    Then "Sunglasses" should no longer appear in "Birthday"
    And "Sunglasses" should appear in "Christmas"

  Scenario: Prevent creating a wishlist without a name
    Given I am creating a new wishlist
    When I submit the form without providing a name
    Then the wishlist should not be created
    And I should see a validation message indicating that a name is required

  Scenario: Persist authenticated customer wishlists
    Given I am logged in as a customer
    And I have created a wishlist named "Birthday"
    And I have added products to "Birthday"
    When I log out
    And I log in again
    Then my "Birthday" wishlist should still exist
    And its previously saved products should still be available

  Scenario: Manage multiple wishlists as a guest
    Given I am browsing the store as a guest
    When I create a wishlist named "Birthday"
    And I create another wishlist named "Christmas"
    And I add a product to "Birthday"
    When I refresh the page
    Then both wishlists should still be available
    And the product should still belong to "Birthday"

  Scenario: Prevent unauthorized access to another customer's wishlist
    Given a customer has a private wishlist named "Birthday"
    When another customer attempts to access that wishlist
    Then they should not be able to view or modify its contents

  Scenario: Add products from a selected wishlist to the cart
    Given I have a wishlist named "Christmas"
    And "Christmas" contains multiple products available for purchase
    When I open "Christmas"
    And I select two products
    And I click "Add Selected to Cart"
    Then both selected products should be added to my shopping cart
    And the products should remain in "Christmas"
    And my other wishlists should remain unchanged
