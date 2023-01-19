<a name="readme-top"></a>



<!-- PROJECT SHIELDS -->
<!--
*** I'm using markdown "reference style" links for readability.
*** Reference links are enclosed in brackets [ ] instead of parentheses ( ).
*** See the bottom of this document for the declaration of the reference variables
*** for contributors-url, forks-url, etc. This is an optional, concise syntax you may use.
*** https://www.markdownguide.org/basic-syntax/#reference-style-links
-->
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]



<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/dead-guru/devichan">
    <img src="https://i.imgur.com/pzQwvyq.gif" alt="Logo" width="80" height="80">
  </a>

<h3 align="center">DeVichan</h3>

  <p align="center">
    Dead Vichan - A Dockerized lightweight and full featured PHP imageboard based on vichan
    <br />
    <a href="https://github.com/dead-guru/devichan/wiki"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://4.dead.guru/">View Demo</a>
    ·
    <a href="https://github.com/dead-guru/devichan/issues">Report Bug</a>
    ·
    <a href="https://github.com/dead-guru/devichan/issues">Request Feature</a>
  </p>
</div>



<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#upgrade">Upgrade</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>



<!-- ABOUT THE PROJECT -->
## About The Project

[![Product Name Screen Shot][product-screenshot]](https://user-images.githubusercontent.com/1472664/211690585-1732c076-4889-447f-88ff-8912b18b4a05.png)

**vichan** is a free light-weight, fast, highly configurable and user-friendly imageboard software package. It is written in PHP and has few dependencies.

**But is old, bad and dead**

So, **DeVichan** - is a hard fork of vichan where we try to fix some stuff.

**New features**:
* All-in-one `docker-compose.yml`
* Updated twig (`1 -> 3`), jquery (`2 -> 3`) and lot others deps
* 404 and 500 error pages
* Banners for each board
* Statistics page (`/stats/` or `stats.php`)
* Removed lot of dead code
* Tons of small fixes of js and templates
* CSS(main style.css and all configured themes) and JS minification
* photon and photon-dark are main supported themes

Of course, it is very difficult to fix code written in PHP5 times many years ago. But we can keep this legacy code safe and minimally up-to-date. Moreover, the conservative position of the original vichan developers worsens the situation even more. I wonder what we can get out of this venture

<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- GETTING STARTED -->
## Getting Started

This is an example of how you may give instructions on setting up your devichan locally.
To get a local copy up and running follow these simple example steps.

### Prerequisites

1) Install Docker
2) Install docker-compose

### Installation

1. Clone the repo
   ```sh
   git clone git@github.com:dead-guru/devichan.git
   ```
2. Run docker-compose
   ```sh
   docker-compose up -d
   ```
3. Install Composer packages
   ```sh
   docker-compose exec cphp composer install
   ```
4. Navigate to `http://localhost/install.php` in your web browser and follow the prompts.
5. devichan should now be installed. Log in to `/mod/` with the default username and password combination: `admin` / `password`.
6. You can install some "themes" on `/mod/?/themes`

**!!!Please remember to change the administrator account password.**

**See also**: [Configuration](https://github.com/dead-guru/devichan/wiki/Configuraion).

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- Upgrade details -->
## Upgrade

To upgrade from any version of Tinyboard or vichan or devichan:

Either run `git pull` to update your files, if you used git, or backup your `inc/instance-config.php`, replace all your files in place (don't remove boards etc.), then put `inc/instance-config.php` back and finally run `install.php`.

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- ROADMAP -->
## Roadmap

- [x] Dockerize
- [x] Update deps
- [ ] Archive Feature (https://github.com/dead-guru/devichan/tree/feature/arhcive1)
- [ ] Cloak IP and hash ip to db
- [ ] migrate to php 8.2

See the [open issues](https://github.com/dead-guru/devichan/issues) for a full list of proposed features (and known issues).

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- CONTRIBUTING -->
## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- LICENSE -->
## License

Distributed under the GNU General Public Licens. See `LICENSE.md` for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

Use this space to list resources you find helpful and would like to give credit to.

* [vichan](https://github.com/vichan-devel/vichan)
* [Tinyboard](https://github.com/savetheinternet/Tinyboard)
* [Twig](https://twig.symfony.com/doc/2.x/)


<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/dead-guru/devichan.svg?style=for-the-badge
[contributors-url]: https://github.com/dead-guru/devichan/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/dead-guru/devichan.svg?style=for-the-badge
[forks-url]: https://github.com/dead-guru/devichan/network/members
[stars-shield]: https://img.shields.io/github/stars/dead-guru/devichan.svg?style=for-the-badge
[stars-url]: https://github.com/dead-guru/devichan/stargazers
[issues-shield]: https://img.shields.io/github/issues/dead-guru/devichan.svg?style=for-the-badge
[issues-url]: https://github.com/dead-guru/devichan/issues
[license-shield]: https://img.shields.io/badge/License-GPLv3-blue.svg?style=for-the-badge
[license-url]: https://github.com/dead-guru/devichan/blob/master/LICENSE.txt
[product-screenshot]: https://user-images.githubusercontent.com/1472664/211690585-1732c076-4889-447f-88ff-8912b18b4a05.png
