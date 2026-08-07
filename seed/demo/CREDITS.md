# Where the demo images come from

Everything in this folder ships with the project and is installed with the
demo data. Only images whose licence allows use without conditions belong
here — the project is passed on to other bands, and none of them should
inherit an obligation nobody told them about.

Two further rules follow from that, and they are not about copyright:

- **No recognisable faces.** CC0 hands over the copyright, not the consent of
  the people in the picture. A demo is a public page, so anyone shown there is
  published — and presented as if they were this band. Close-up portraits were
  therefore dropped even where the licence was clean.
- **Every file is re-encoded**, never passed through as downloaded. That drops
  the camera metadata along the way, which is where locations and device names
  would otherwise sit.

## stage-crowd.jpg

- **Shows:** a band on a lit stage with the audience in silhouette
- **Source:** https://www.pexels.com/photo/panoramic-view-of-crowd-at-music-concert-248963/
- **Photographer:** Pixabay (via Pexels)
- **Licence:** CC0 1.0 — public domain dedication, no attribution required,
  commercial use permitted
- **Changes:** scaled to 2000 px wide and re-encoded as JPEG at quality 82

Used twice: as the background of the public page and, until v1.213.0, as the
single gallery picture.

## The gallery pictures (added in v1.213.0)

All six come from Wikimedia Commons, all under **CC0 1.0**, all originally
uploaded to Unsplash before its licence change and mass-transferred to Commons
under CC0. Each was scaled to 1600 px on the long edge and re-encoded as JPEG
at quality 80 — which puts every file at the weight of the background image
above, roughly 100 to 460 KB.

| File | Shows | Photographer | Commons file |
|---|---|---|---|
| `stage-openair.jpg` | open-air stage at night, lights over a distant crowd | Redd Angelo | [Concert in Gallagher Park (Unsplash).jpg](https://commons.wikimedia.org/wiki/File:Concert_in_Gallagher_Park_(Unsplash).jpg) |
| `stage-bw.jpg` | black and white, a performer at the stage edge above the crowd | Felix Russell-Saw | [Rock concert in black and white (Unsplash).jpg](https://commons.wikimedia.org/wiki/File:Rock_concert_in_black_and_white_(Unsplash).jpg) |
| `from-the-stage.jpg` | a performer in backlight and haze, silhouette only | Axel Antas-Bergkvist | [Performing For The Crowd (Unsplash XUdIi04ohps).jpg](https://commons.wikimedia.org/wiki/File:Performing_For_The_Crowd_(Unsplash_XUdIi04ohps).jpg) |
| `crowd-frontrow.jpg` | audience with raised hands in blue stage light | Melanie van Leeuwen | [Front row audience (Unsplash).jpg](https://commons.wikimedia.org/wiki/File:Front_row_audience_(Unsplash).jpg) |
| `crowd-hands.jpg` | black and white, raised hands in a crowd, faces in shadow | Hannah Rodrigo | [Concert crowd (Unsplash).jpg](https://commons.wikimedia.org/wiki/File:Concert_crowd_(Unsplash).jpg) |
| `mic-studio.jpg` | a condenser microphone on its mount, dark background | Kelly Sikkema | [Blue condenser microphone (Unsplash).jpg](https://commons.wikimedia.org/wiki/File:Blue_condenser_microphone_(Unsplash).jpg) |

`crowd-hands.jpg` is installed twice, from two different origin folders. That is
deliberate: it gives the duplicate finder a real find instead of an empty list,
and it costs no second file.

The credits are given here although CC0 does not ask for them: whoever takes
over this project should be able to check where a file came from without having
to search for it.

Since v1.214.0 the photographers are also named **in the running application** —
in the imprint and under the public gallery — for as long as the pictures are
actually in use. That list lives in `DEMO_PHOTO_CREDITS` in `app/demo.php` and
must be changed together with this file. Two lists that drift apart are worse
than one.
