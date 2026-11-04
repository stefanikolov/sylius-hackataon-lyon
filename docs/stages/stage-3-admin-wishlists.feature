Feature: Wishlist overview in the administration panel (Stage 3)
  As a store administrator
  I want to browse customer wishlists in the administration panel
  So that I can see which products customers have saved

  # Read-only. All Stage 1 and Stage 2 storefront scenarios must keep working.

  Scenario: View wishlists in the administration panel
    Given I am logged in as a store administrator
    And customers have created wishlists
    When I navigate to the wishlist section in the administration panel
    Then I should see a list of existing wishlists
    And each wishlist should display its name
    And each wishlist should display its owner
    And each wishlist should display the number of products it contains
    And I should be able to open its details

  Scenario: View multiple wishlists belonging to one customer
    Given a customer named "John Doe" has created the following wishlists:
      | Name         | Product count |
      | My Favorites | 3             |
      | Birthday     | 5             |
      | Christmas    | 2             |
    When I open the wishlist listing in the administration panel
    Then I should see all three wishlists
    And each wishlist should be associated with "John Doe"
    And each wishlist should display its respective product count

  Scenario: View wishlist details
    Given a customer has created a wishlist named "Birthday"
    And the wishlist contains the following products:
      | Product    |
      | T-Shirt    |
      | Sunglasses |
    When I open the details of the "Birthday" wishlist
    Then I should see the wishlist name "Birthday"
    And I should see the wishlist owner
    And I should see "T-Shirt" and "Sunglasses"
    And I should see the total number of products in the wishlist

  Scenario: View wishlist product information
    Given a customer has a wishlist containing multiple products
    When I open the wishlist details in the administration panel
    Then each product should display its name
    And each product should display its image
    And each product should display its current price
    And I should be able to identify the products saved by the customer

  Scenario: Identify an authenticated customer
    Given a registered customer named "John Doe" owns a wishlist
    And the customer's email address is "john@example.com"
    When I view that wishlist in the administration panel
    Then I should see "John Doe" as its owner
    And I should see "john@example.com" as the customer's email address

  Scenario: Identify a guest wishlist
    Given a guest customer has created a wishlist named "My Favorites"
    When I view the wishlist listing in the administration panel
    Then the wishlist should be visible
    And its owner should be identified as "Guest"
    And I should be able to open its details

  Scenario: View an empty wishlist
    Given a customer has created a wishlist named "Birthday"
    And the wishlist contains no products
    When I open the details of the "Birthday" wishlist
    Then I should see the wishlist name and owner
    And I should see an empty wishlist message
    And the product count should be 0

  Scenario: Search wishlists by customer
    Given multiple customers have created wishlists
    And a customer named "John Doe" owns two wishlists
    When I search for wishlists belonging to "John Doe"
    Then I should see the two wishlists owned by "John Doe"
    And wishlists belonging to other customers should not appear

  Scenario: Restrict wishlist administration to administrators
    Given I am logged in as a regular storefront customer
    When I attempt to access the wishlist administration section
    Then access should be denied
    And I should not be able to view other customers' wishlists

  Scenario: Ensure the administration interface is read-only
    Given I am logged in as a store administrator
    And I am viewing a customer's wishlist
    When I inspect the available actions
    Then I should be able to view the wishlist and its products
    But I should not be able to modify the wishlist name
    And I should not be able to add or remove products
    And I should not be able to delete the wishlist
