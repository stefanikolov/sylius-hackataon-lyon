Feature: Basic product wishlist (Stage 1 - MVP)
  As a customer
  I want to save products to a personal wishlist
  So that I can find them later and add them to my cart when I am ready to buy

  # The wishlist works for guests and logged-in customers and is independent of the cart.

  Scenario: Add a product to the wishlist from PDP
    Given I am browsing a product detail page
    When I click the "Add to Wishlist" button
    Then the product should be added to my wishlist
    And I should see a confirmation that the product was added

  Scenario: Add a product to the wishlist from PLP
    Given I am browsing a product listing page
    When I click the "Add to Wishlist" button for a product
    Then the selected product should be added to my wishlist
    And I should see a confirmation that the product was added

  Scenario: Prevent duplicate wishlist entries
    Given a product is already in my wishlist
    When I attempt to add the same product again
    Then the product should not be duplicated
    And my wishlist should contain only one entry for that product

  Scenario: Guest customer wishlist
    Given I am browsing the store as a guest
    When I add a product to my wishlist
    Then the product should be saved in my guest wishlist
    And I should be able to access it while browsing the store
    And my wishlist should remain available after refreshing the page

  Scenario: Authenticated customer wishlist
    Given I am logged in as a customer
    And I have added products to my wishlist
    When I log out and log in again
    Then my previously saved wishlist products should still be available

  Scenario: View wishlist contents
    Given I have added multiple products to my wishlist
    When I navigate to my wishlist page
    Then I should see all products saved in my wishlist
    And each product should display its name, image, and current price
    And each product should have an option to remove it
    And each product should have an option to add it to my cart

  Scenario: Display an empty wishlist
    Given my wishlist does not contain any products
    When I navigate to my wishlist page
    Then I should see an appropriate empty wishlist message
    And I should be able to continue shopping

  Scenario: Remove a product from the wishlist
    Given my wishlist contains multiple products
    When I remove one of the products
    Then the selected product should disappear from my wishlist
    And all other wishlist products should remain unchanged

  Scenario: Add a single wishlist product to the cart
    Given my wishlist contains a product available for purchase
    When I click "Add to Cart" for that product
    Then the selected product should be added to my shopping cart
    And the product should remain in my wishlist
    And the shopping cart should reflect the newly added product

  Scenario: Add multiple wishlist products to the cart
    Given my wishlist contains multiple products available for purchase
    And I have selected two or more products
    When I click "Add Selected to Cart"
    Then all selected products should be added to my shopping cart
    And products that were not selected should not be added
    And all products should remain in my wishlist

  Scenario: Handle a product unavailable for purchase
    Given my wishlist contains a product that is no longer available for purchase
    When I view my wishlist
    Then the product should remain visible in my wishlist
    And I should not be able to add the unavailable product to my cart
    And I should see an indication that the product is unavailable
