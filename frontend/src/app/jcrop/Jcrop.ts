/**
 * jquery.Jcrop.js v0.9.12
 * jQuery Image Cropping Plugin - released under MIT License
 * Author: Kelly Hallman <khallman@gmail.com>
 * http://github.com/tapmodo/Jcrop
 * Copyright (c) 2008-2013 Tapmodo Interactive LLC {{{
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * }}}
 *
 * Every piece of the vendored plugin this monorepo actually uses - the orchestrating factory
 * function, the document-level drag-tracking/touch/selection-box state (what used to be separate
 * Tracker, Touch, and Selection classes), and finally Coords, the one collaborator that ever owned a
 * distinct piece of state of its own - now lives directly on JcropComponent (jcrop.component.ts).
 * There's only ever one consumer, so keeping any of it as a separate class/module here was pure
 * indirection; Coords held out the longest only because it genuinely owned state the others didn't,
 * but once it needed its own bounds/scale driven directly by JcropComponent's own signals rather
 * than being handed pre-computed numbers once at construction, keeping it separate just added a
 * layer of indirection between the two, so it moved too - see the "vendored plugin's own single
 * closure-based factory function" comment there for the full history. JcropCrop is the one thing
 * left here: still a real shared type, not just an internal implementation detail, since it's also
 * imported directly by the three pages that embed JcropComponent for cropping (upload/crop,
 * moder/pictures/item/crop, moder/pictures/item/area) to type the crop rect they read back out via
 * (cropChange) and pass back in via [initialCrop].
 */

export interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}
