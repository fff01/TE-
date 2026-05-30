<section class="browse-panel">
  <h3>Filters</h3>
  <div class="browse-filter-grid">
    <div class="browse-filter-group">
      <div class="browse-filter-label">Keyword</div>
      <div class="te-autocomplete" data-te-autocomplete-root>
        <input class="browse-filter-input" id="browseKeyword" type="text" placeholder="Search TE names or labels" data-te-autocomplete>
        <button class="te-autocomplete-toggle" type="button" aria-label="Show TE names" aria-expanded="false" data-te-autocomplete-toggle></button>
        <div class="te-autocomplete-menu" data-te-autocomplete-menu hidden></div>
      </div>
    </div>
    <div class="browse-filter-group">
      <div class="browse-filter-label">Class</div>
      <select class="browse-filter-select" id="browseClass">
        <option value="">All classes</option>
      </select>
    </div>
    <div class="browse-filter-group">
      <div class="browse-filter-label">Family</div>
      <select class="browse-filter-select" id="browseFamily">
        <option value="">All families</option>
      </select>
    </div>
    <div class="browse-filter-group">
      <div class="browse-filter-label">Subtype</div>
      <select class="browse-filter-select" id="browseSubtype">
        <option value="">All subtypes</option>
      </select>
    </div>
    <div class="browse-filter-actions">
      <button class="browse-filter-btn is-primary" id="browseApplyBtn" type="button">Apply</button>
      <button class="browse-filter-btn" id="browseResetBtn" type="button">Reset</button>
    </div>
  </div>
</section>
