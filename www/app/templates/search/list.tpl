{extends "../index.tpl"}
{block "title"}Groups Around Me - Search{/block}
{block "main-content"}
    <h1>Search</h1>

    {literal}
    <div id="search">
        <ul class="menu">
            <li><a href="" data-bind="click: function() { this.searchType('groups'); }">Groups</a></li>
            <li><a href="" data-bind="click: function() { this.searchType('users'); }">Users</a></li>
        </ul>

        <div data-bind="with: groupsSearchViewModel, visible: searchType() == 'groups'">
            <form data-bind="submit: search">
                <input data-bind="value: countryId" name="country_id" type="hidden" placeholder="Country ID" />
                <input data-bind="value: regionId" name="region_id" type="hidden" placeholder="Region ID" />
                <input data-bind="value: cityId" name="city_id" type="hidden" placeholder="City ID" />

                <div class="row">
                    <input data-bind="value: distance" name="distance" type="text" placeholder="Distance" />
                </div>
                <div class="row">
                    <select data-bind="foreach: countries, value: countryId">
                        <option data-bind="text: name, value: id"></option>
                    </select>
                </div>
                <div class="row">
                    <select data-bind="foreach: regions, value: regionId, visible: regions().length > 0">
                        <option data-bind="text: name, value: id"></option>
                    </select>
                </div>
                <div class="row">
                    <select data-bind="foreach: cities, value: cityId, visible: cities().length > 0">
                        <option data-bind="text: name, value: id"></option>
                    </select>
                </div>
                <div class="row">
                    <input data-bind="value: minMembers" name="min_members" type="text" placeholder="Min Members Count" />
                </div>
                <div class="row">
                    <input data-bind="value: title" name="title" type="text" placeholder="Title" />
                </div>
                <div class="row">
                    <div class="tokenizer">
                        <div class="token-input">
                            <input data-bind="value: tags" type="hidden" name="tags" />
                            <input class="text" placeholder="Tag" type="text" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <input type="submit" />
                </div>
            </form>

            <div data-bind="if: show">
                <ul class="menu">
                    <li><a data-bind="click: function() { this.type('list'); }">List</a></li>
                    <li><a data-bind="click: function() { this.type('map'); }">Map</a></li>
                </ul>

                <div data-bind="if: type() == 'list'">
                    <div class="groups">
                        <div data-bind="foreach: entries">

                            <div class="group-item">
                                <div class="image">
                                    <img data-bind="attr: {src: image}, visible: image != ''" height="50" width="50" />
                                </div>

                                <div class="detail">
                                    <a data-bind="text: title, attr: {href: link}"></a>

                                    <div>
                                        <span data-bind="if: distance != 0">Distance: <span data-bind="text: distance"></span> miles, </span>
                                        <span data-bind="text: postsCount"></span> posts, <span data-bind="text: members"></span> members
                                    </div>
                                </div>
                            </div>

                        </div>
                        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    </div>
                    <h2 data-bind="visible: !hasEntries() && !loading()">
                        <div>No search results</div>
                    </h2>

                    <div data-bind="visible: !hasEntries() && !loading() && title() != '' && distance() != '' && minMembers() == ''">
                        <div class="group-item">
                            <div class="image">
                                <img height="50" width="50" />
                            </div>

                            <div class="detail">
                                <a data-bind="text: title(), click: create"></a>
                                <div>
                                    <span>This group will be created.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div data-bind="if: type() == 'map'">
                    <div class="groups">
                        <div data-bind="foreach: entries">
                            <div class="item-name">
                                <img data-bind="attr: {src: image}" height="20" width="20" />
                                <a data-bind="text: title, attr: {href: link}"></a>
                                <div>Distance: <span data-bind="text: distance"></span> miles</div>
                                <div>Members: <span data-bind="text: members"></span></div>
                                <div>Posts: <span data-bind="text: postsCount"></span></div>
                                <div>Location: <span data-bind="text: location.longitude"></span> <span data-bind="text: location.latitude"></span></div>
                            </div>
                        </div>
                        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    </div>
                    <h2 data-bind="visible: !hasEntries() && !loading()">
                        <div>No search results</div>
                    </h2>
                </div>
            </div>
        </div>


        <div data-bind="with: usersSearchViewModel, visible: searchType() == 'users'">
            <form data-bind="submit: search">
                <input data-bind="value: countryId" name="country_id" type="hidden" placeholder="Country ID" />
                <input data-bind="value: regionId" name="region_id" type="hidden" placeholder="Region ID" />
                <input data-bind="value: cityId" name="city_id" type="hidden" placeholder="City ID" />

                <div class="row">
                    <input data-bind="value: distance" name="distance" type="text" placeholder="Distance" />
                </div>
                <div class="row">
                    <select data-bind="foreach: countries, value: countryId">
                        <option data-bind="text: name, value: id"></option>
                    </select>
                </div>
                <div class="row">
                    <select data-bind="foreach: regions, value: regionId, visible: regions().length > 0">
                        <option data-bind="text: name, value: id"></option>
                    </select>
                </div>
                <div class="row">
                    <select data-bind="foreach: cities, value: cityId, visible: cities().length > 0">
                        <option data-bind="text: name, value: id"></option>
                    </select>
                </div>
                <div class="row">
                    <input data-bind="value: name" name="name" type="text" placeholder="Name" />
                </div>
                <div class="row">
                    <input type="submit" />
                </div>
            </form>

            <div data-bind="if: show">
                <ul class="menu">
                    <li><a data-bind="click: function() { this.type('list'); }">List</a></li>
                    <li><a data-bind="click: function() { this.type('map'); }">Map</a></li>
                </ul>

                <div data-bind="if: type() == 'list'">
                    <div class="users">
                        <div data-bind="foreach: entries">

                            <div class="user-item">
                                <div class="image">
                                    <img data-bind="attr: {src: image}, visible: image != ''" height="35" width="35" />
                                </div>

                                <div class="detail">
                                    <a data-bind="text: name, attr: {href: link}"></a>
                                    <span data-bind="if: distance != 0" style="margin-left: 20px;">Distance: <span data-bind="text: distance"></span> miles</span>
                                </div>
                            </div>

                        </div>
                        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    </div>
                    <h2 data-bind="visible: !hasEntries() && !loading()">
                        <div>No search results</div>
                    </h2>
                </div>

                <div data-bind="if: type() == 'map'">
                    <div class="users">
                        <div data-bind="foreach: entries">
                            <div class="item-name">
                                <img data-bind="attr: {src: image}" height="20" width="20" />
                                <a data-bind="text: name, attr: {href: link}"></a>
                                <div>Distance: <span data-bind="text: distance"></span> miles</div>
                                <div>Location: <span data-bind="text: location.longitude"></span> <span data-bind="text: location.latitude"></span></div>
                            </div>
                        </div>
                        <button data-bind="visible: hasMore(), click: loadMore, css: {wait: loading()}" class="button">Load more</button>
                    </div>
                    <h2 data-bind="visible: !hasEntries() && !loading()">
                        <div>No search results</div>
                    </h2>
                </div>
            </div>
        </div>

    </div>

    <script type="text/javascript">
        ko.applyBindings(new SearchViewModel(), document.getElementById("search"));
    </script>
    {/literal}
{/block}