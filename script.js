/*
wasaver
Copyright (C) 2020  cressidacressida

This file is part of wasaver.

wasaver is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

wasaver is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with wasaver.  If not, see <https://www.gnu.org/licenses/>.
*/

function scrollToTop(id) {
    document.getElementById(id).scrollTo(0, 0);
}

function scrollToBottom(id) {
    var div = document.getElementById(id);
    div.scrollTo(0, div.scrollHeight);
}

function showDropDownDate() {
    var dropdown = document.getElementById("dropdown-content-date");
    dropdown.classList.toggle("show");
    if(dropdown.classList.contains("show"))
        dropdown.getElementsByClassName("highlight")[0].scrollIntoView({block: "center"});
}

window.onclick = function(event) {
    var dropdown = document.getElementById("dropdown-content-date");
    if(! (document.getElementById("dropdown-button-date").contains(event.target) ||
          dropdown.contains(event.target))) {
        if (dropdown.classList.contains("show"))
            dropdown.classList.remove("show");
    }
}

var main;
var list;
var alists;

document.addEventListener("DOMContentLoaded", function() {
    main = document.getElementById("main");
    list = document.getElementsByClassName("date");
    var elements = [document.getElementById("dropdown-content-date"),
                document.getElementById("sidebar")];
    alists = elements.map(x => x.getElementsByClassName("date-link"));
    main.addEventListener("scroll", onScroll)
    onScroll();
});

var alast = -1;
var last = -1;
var running = 0;
var no_link_update = 0;

function onScroll() {
    if(running)
        return;
    else
        running = 1;
    var scroll = main.scrollTop;
    for(i = list.length - 1; i >= 0; i--) {
        var offset = list[i].offsetTop;
        if(offset < scroll + 100) {
            if(i != last) {
                if(last != -1)
                    list[last].classList.remove("sticky");
                list[i].classList.add("sticky");
                last = i;
            }
            break;
        }
    }
    if(! no_link_update) {
        for(i = 0; i < list.length; i++) {
            var offset = list[i].offsetTop;
            if(offset > scroll) {
                if(i != alast) {
                    alists.forEach(alist => {
                        if(alast != -1)
                            alist[alast].classList.remove("highlight");
                        alist[i].classList.add("highlight");
                    });
                    alast = i;
                }
                break;
            }
        }
    }
    no_link_update = 0;
    running = 0;
}

function scrollToId(id) {
    var i;
    for(i = 0; i < alists[0].length; i++) {
        if(alists[0][i].id == id.concat("_link"))
            break;
    }
    if(i != alast) {
        alists.forEach(alist => alist[alast].classList.remove("highlight"));
        alast = i;
        alists.forEach(alist => alist[alast].classList.add("highlight"));
        no_link_update = 1;
        document.getElementById(id).scrollIntoView(true);
    }
}
